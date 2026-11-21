#include <Arduino.h>
#include "Config.h"
#include "MyFile.h"
#include "MyWifi.h"
#include "PlatformClient.h"

// ==============================================================================
// State Variables & Buffers
// ==============================================================================
static unsigned long prevBlinkMillis = 0;
static const unsigned long blinkInterval = 1000;
static bool ledState = false;

static String inputBuffer = "";

// CLI Interactive States for Wi-Fi Wizard
enum CliState {
  CLI_STATE_NORMAL,
  CLI_STATE_WAIT_SSID_SELECT,
  CLI_STATE_WAIT_PASSWORD
};

static CliState cliState = CLI_STATE_NORMAL;
static String selectedSSID = "";

// ==============================================================================
// Menu & Help Screens
// ==============================================================================
void printMenu() {
  Serial.println("\n---------------------------------------------------------------");
  Serial.println("         ESP32 SENSOR PLATFORM & STORAGE CONTROLLER");
  Serial.printf ("  Target Board : %s\n", BOARD_NAME);
  Serial.printf ("  Server URL   : %s\n", platformGetServerUrl().c_str());
  Serial.printf ("  Device ID    : %s\n", platformGetSensorId().c_str());
  Serial.printf ("  Config Ver   : %s\n", platformGetConfigVersion().c_str());
  Serial.printf ("  Code Ver     : %s\n", platformGetCodeVersion().c_str());
  Serial.printf ("  Bearer Token : %s\n", platformIsRegistered() 
                 ? (platformGetToken().substring(0, min(12, (int)platformGetToken().length())) + "...").c_str() 
                 : "NOT REGISTERED");
  Serial.printf ("  Wi-Fi Status : %s\n", wifiIsConnected() ? "CONNECTED" : "DISCONNECTED");
  Serial.println("---------------------------------------------------------------");
  Serial.println("QUICK ACTIONS (Type number & press Enter):");
  Serial.println("  [1] Send Heartbeat / Ping      (Syncs version & status to website)");
  Serial.println("  [2] Upload Telemetry File      (Uploads to /api/v1/sensors/upload)");
  Serial.println("  [3] Check Configuration Update (Downloads active config file)");
  Serial.println("  [4] Check / Download Code File (Downloads active sensor code file)");
  Serial.println("  [5] Re-Register Sensor         (Clears token & registers freshly)");
  Serial.println("  [6] Scan & Connect Wi-Fi       (Interactive network wizard)");
  Serial.println("  [7] Flash Storage Info & Files (LittleFS df & ls)");
  Serial.println("  [M] Re-display this menu");
  Serial.println("  [H] Display full CLI command reference");
  Serial.println("---------------------------------------------------------------");
}

void printHelp() {
  Serial.println("\n================ FULL CLI COMMAND REFERENCE ================");
  Serial.println("WI-FI COMMANDS:");
  Serial.println("  wifi scan                 Interactive scan & connection wizard");
  Serial.println("  wifi select <num> [pass]  Select network by number from scan list");
  Serial.println("  wifi status               Display Wi-Fi connection info & IP");
  Serial.println("  wifi connect [ssid] [pw]  Connect to network (uses saved if blank)");
  Serial.println("  wifi set <ssid> <pw>      Manually save credentials to flash NVS");
  Serial.println("  wifi clear                Clear saved Wi-Fi credentials");
  Serial.println("  wifi disconnect           Disconnect Wi-Fi");
  Serial.println();
  Serial.println("SENSOR PLATFORM COMMANDS:");
  Serial.println("  register                  Register device at server platform");
  Serial.println("  heartbeat | ping          Send heartbeat & sync version to dashboard");
  Serial.println("  version                   Check config version on server");
  Serial.println("  download [config|code]    Download active config or code file");
  Serial.println("  code check                Check sensor code version on server");
  Serial.println("  code download             Download active sensor code file");
  Serial.println("  upload [local_path]       Upload telemetry file (or sample data)");
  Serial.println("  reregister                Clear token and register freshly");
  Serial.println("  status                    Display platform & registration status");
  Serial.println("  device set <device_id>    Update sensor ID (e.g. ESP32-DEVICE-001)");
  Serial.println("  server set <server_url>   Update server base URL");
  Serial.println();
  Serial.println("FILE SYSTEM (LITTLEFS) COMMANDS:");
  Serial.println("  ls [path]                 List files in directory");
  Serial.println("  cat <file>                Print content of file");
  Serial.println("  write <file> <text>       Overwrite file with text");
  Serial.println("  append <file> <text>      Append text to file");
  Serial.println("  rm <file>                 Remove file");
  Serial.println("  mkdir <dir>               Create directory");
  Serial.println("  rmdir <dir>               Remove empty directory");
  Serial.println("  mv <from> <to>            Rename / move file");
  Serial.println("  df                        Display flash storage usage");
  Serial.println("  format yes                Format flash partition");
  Serial.println();
  Serial.printf ("SENSOR HARDWARE SERIAL (RX:%d, TX:%d):\n", PIN_SENSOR_RX, PIN_SENSOR_TX);
  Serial.println("  sensor send <text>        Send text to external sensor");
  Serial.println("  sensor flush              Clear sensor RX buffer");
  Serial.println();
  Serial.println("SYSTEM:");
  Serial.println("  menu                      Display quick menu");
  Serial.println("  help                      Display this command list");
  Serial.println("  reboot                    Restart ESP32");
  Serial.println("============================================================");
}

// ==============================================================================
// Interactive Wi-Fi Wizard Handler
// ==============================================================================
void handleSsidSelection(int choice, const char* directPassword = nullptr) {
  int totalFound = wifiGetScannedCount();
  if (choice < 1 || choice > totalFound) {
    Serial.printf("Invalid choice (%d). Please enter a number between 1 and %d (or 'cancel'):\n", choice, totalFound);
    Serial.print("Select network: ");
    return;
  }

  selectedSSID = wifiGetScannedSSID(choice);
  bool isOpen = wifiIsScannedOpen(choice);

  if (directPassword != nullptr && strlen(directPassword) > 0) {
    Serial.printf("[WiFi] Selected '%s'. Connecting...\n", selectedSSID.c_str());
    wifiSaveCredentials(selectedSSID.c_str(), directPassword);
    if (wifiConnect(selectedSSID.c_str(), directPassword)) {
      // Once Wi-Fi is connected, automatically sync with platform
      if (!platformIsRegistered()) {
        platformRegister();
      } else {
        platformHeartbeat();
      }
    }
    cliState = CLI_STATE_NORMAL;
    printMenu();
    Serial.print("esp32> ");
    return;
  }

  if (isOpen) {
    Serial.printf("[WiFi] Selected '%s' (Open Network).\n", selectedSSID.c_str());
    wifiSaveCredentials(selectedSSID.c_str(), "");
    if (wifiConnect(selectedSSID.c_str(), "")) {
      if (!platformIsRegistered()) {
        platformRegister();
      } else {
        platformHeartbeat();
      }
    }
    cliState = CLI_STATE_NORMAL;
    printMenu();
    Serial.print("esp32> ");
  } else {
    Serial.printf("[WiFi] Selected '%s'.\n", selectedSSID.c_str());
    Serial.printf("Enter password for '%s' (or press Enter if blank, 'cancel' to abort): ", selectedSSID.c_str());
    cliState = CLI_STATE_WAIT_PASSWORD;
  }
}

// ==============================================================================
// Command Parser
// ==============================================================================
void parseCommand(String cmd) {
  cmd.trim();
  if (cmd.length() == 0) return;

  // --- Interactive State Machine Handler ---
  if (cliState == CLI_STATE_WAIT_SSID_SELECT) {
    if (cmd.equalsIgnoreCase("cancel") || cmd.equalsIgnoreCase("abort") || cmd.equalsIgnoreCase("exit")) {
      Serial.println("[WiFi] Selection cancelled.");
      cliState = CLI_STATE_NORMAL;
      Serial.print("esp32> ");
      return;
    }
    int choice = cmd.toInt();
    if (choice > 0) {
      handleSsidSelection(choice);
    } else {
      Serial.println("Please enter a valid network number (or 'cancel'):");
      Serial.print("Select network: ");
    }
    return;
  }

  if (cliState == CLI_STATE_WAIT_PASSWORD) {
    if (cmd.equalsIgnoreCase("cancel") || cmd.equalsIgnoreCase("abort")) {
      Serial.println("[WiFi] Setup cancelled.");
      cliState = CLI_STATE_NORMAL;
      selectedSSID = "";
      Serial.print("esp32> ");
      return;
    }
    String pass = cmd;
    Serial.printf("\n[WiFi] Saving credentials for '%s' and connecting...\n", selectedSSID.c_str());
    wifiSaveCredentials(selectedSSID.c_str(), pass.c_str());
    if (wifiConnect(selectedSSID.c_str(), pass.c_str())) {
      // Once Wi-Fi is connected, auto-sync platform
      if (!platformIsRegistered()) {
        platformRegister();
      } else {
        platformHeartbeat();
      }
    }
    cliState = CLI_STATE_NORMAL;
    selectedSSID = "";
    printMenu();
    Serial.print("esp32> ");
    return;
  }

  // --- Quick Number Shortcuts ---
  if (cmd == "1") {
    platformHeartbeat();
    return;
  }
  if (cmd == "2") {
    platformUploadFile();
    return;
  }
  if (cmd == "3") {
    platformVersionCheck();
    return;
  }
  if (cmd == "4") {
    platformCodeVersionCheck();
    return;
  }
  if (cmd == "5") {
    platformReRegister();
    return;
  }
  if (cmd == "6") {
    int found = wifiScan();
    if (found > 0) {
      Serial.println("Enter network number to connect (or 'cancel'):");
      Serial.print("Select network [1-");
      Serial.print(found);
      Serial.print("]: ");
      cliState = CLI_STATE_WAIT_SSID_SELECT;
    }
    return;
  }
  if (cmd == "7") {
    fsInfo();
    fsList("/");
    return;
  }
  if (cmd.equalsIgnoreCase("M") || cmd.equalsIgnoreCase("menu")) {
    printMenu();
    return;
  }
  if (cmd.equalsIgnoreCase("H") || cmd.equalsIgnoreCase("help") || cmd == "?") {
    printHelp();
    return;
  }

  // --- Normal CLI Command Dispatcher ---
  int firstSpace = cmd.indexOf(' ');
  String action = (firstSpace == -1) ? cmd : cmd.substring(0, firstSpace);
  String args = (firstSpace == -1) ? "" : cmd.substring(firstSpace + 1);
  args.trim();

  // 1. Wi-Fi Commands
  if (action.equalsIgnoreCase("wifi")) {
    int subSpace = args.indexOf(' ');
    String subCmd = (subSpace == -1) ? args : args.substring(0, subSpace);
    String subArgs = (subSpace == -1) ? "" : args.substring(subSpace + 1);
    subArgs.trim();

    if (subCmd.equalsIgnoreCase("scan") || subCmd.equalsIgnoreCase("search")) {
      int found = wifiScan();
      if (found > 0) {
        Serial.println("Enter network number to connect (or 'cancel'):");
        Serial.print("Select network [1-");
        Serial.print(found);
        Serial.print("]: ");
        cliState = CLI_STATE_WAIT_SSID_SELECT;
        return;
      }
    } 
    else if (subCmd.equalsIgnoreCase("select")) {
      if (wifiGetScannedCount() == 0) {
        Serial.println("Please run 'wifi scan' first to discover networks.");
        return;
      }
      int pSpace = subArgs.indexOf(' ');
      String numStr = (pSpace == -1) ? subArgs : subArgs.substring(0, pSpace);
      String pass = (pSpace == -1) ? "" : subArgs.substring(pSpace + 1);
      pass.trim();

      int choice = numStr.toInt();
      if (choice > 0) {
        handleSsidSelection(choice, pass.length() > 0 ? pass.c_str() : nullptr);
        return;
      } else {
        Serial.println("Usage: wifi select <number> [password]");
      }
    }
    else if (subCmd.equalsIgnoreCase("status")) {
      wifiStatus();
    } 
    else if (subCmd.equalsIgnoreCase("disconnect")) {
      wifiDisconnect();
    } 
    else if (subCmd.equalsIgnoreCase("clear")) {
      wifiClearCredentials();
    } 
    else if (subCmd.equalsIgnoreCase("connect")) {
      int pSpace = subArgs.indexOf(' ');
      if (pSpace != -1) {
        String s = subArgs.substring(0, pSpace);
        String p = subArgs.substring(pSpace + 1);
        p.trim();
        wifiConnect(s.c_str(), p.c_str());
      } else if (subArgs.length() > 0) {
        wifiConnect(subArgs.c_str(), "");
      } else {
        wifiConnect(nullptr, nullptr);
      }
    } 
    else if (subCmd.equalsIgnoreCase("set")) {
      int pSpace = subArgs.indexOf(' ');
      if (pSpace == -1) {
        Serial.println("Usage: wifi set <ssid> <password>");
      } else {
        String s = subArgs.substring(0, pSpace);
        String p = subArgs.substring(pSpace + 1);
        p.trim();
        wifiSaveCredentials(s.c_str(), p.c_str());
      }
    } 
    else {
      Serial.println("Unknown wifi command. Type 'help'.");
    }
  }

  // 2. Platform Commands
  else if (action.equalsIgnoreCase("register")) {
    platformRegister();
  }
  else if (action.equalsIgnoreCase("heartbeat") || action.equalsIgnoreCase("ping")) {
    platformHeartbeat();
  }
  else if (action.equalsIgnoreCase("version")) {
    platformVersionCheck();
  }
  else if (action.equalsIgnoreCase("download")) {
    if (args.equalsIgnoreCase("code")) {
      platformDownloadCode();
    } else if (args.equalsIgnoreCase("config")) {
      platformDownloadConfig();
    } else {
      platformDownloadConfig(args.length() > 0 ? args.c_str() : nullptr);
    }
  }
  else if (action.equalsIgnoreCase("code")) {
    if (args.equalsIgnoreCase("download")) {
      platformDownloadCode();
    } else if (args.equalsIgnoreCase("check") || args.equalsIgnoreCase("version")) {
      platformCodeVersionCheck();
    } else {
      Serial.println("Usage: code check | code download");
    }
  }
  else if (action.equalsIgnoreCase("upload")) {
    platformUploadFile(args.length() > 0 ? args.c_str() : nullptr);
  }
  else if (action.equalsIgnoreCase("reregister")) {
    platformReRegister();
  }
  else if (action.equalsIgnoreCase("status")) {
    platformStatus();
  }
  else if (action.equalsIgnoreCase("device")) {
    if (args.startsWith("set ")) {
      platformSetSensorId(args.substring(4).c_str());
    } else {
      Serial.println("Usage: device set <DEVICE_ID>");
    }
  }
  else if (action.equalsIgnoreCase("server")) {
    if (args.startsWith("set ")) {
      platformSetServerUrl(args.substring(4).c_str());
    } else {
      Serial.println("Usage: server set <URL>");
    }
  }

  // 3. File System Commands
  else if (action.equalsIgnoreCase("ls")) {
    fsList(args.length() > 0 ? args.c_str() : "/");
  } 
  else if (action.equalsIgnoreCase("cat")) {
    if (args.length() == 0) Serial.println("Usage: cat <file>");
    else fsCat(args.c_str());
  } 
  else if (action.equalsIgnoreCase("write") || action.equalsIgnoreCase("append")) {
    bool isAppend = action.equalsIgnoreCase("append");
    int nextSpace = args.indexOf(' ');
    if (nextSpace == -1) {
      Serial.printf("Usage: %s <path> <text>\n", action.c_str());
    } else {
      String path = args.substring(0, nextSpace);
      String text = args.substring(nextSpace + 1);
      fsWrite(path.c_str(), text.c_str(), isAppend);
    }
  } 
  else if (action.equalsIgnoreCase("rm")) {
    if (args.length() == 0) Serial.println("Usage: rm <file>");
    else fsRemove(args.c_str());
  } 
  else if (action.equalsIgnoreCase("mkdir")) {
    if (args.length() == 0) Serial.println("Usage: mkdir <dir>");
    else fsMkdir(args.c_str());
  } 
  else if (action.equalsIgnoreCase("rmdir")) {
    if (args.length() == 0) Serial.println("Usage: rmdir <dir>");
    else fsRmdir(args.c_str());
  } 
  else if (action.equalsIgnoreCase("mv")) {
    int nextSpace = args.indexOf(' ');
    if (nextSpace == -1) {
      Serial.println("Usage: mv <from> <to>");
    } else {
      String from = args.substring(0, nextSpace);
      String to = args.substring(nextSpace + 1);
      to.trim();
      fsRename(from.c_str(), to.c_str());
    }
  } 
  else if (action.equalsIgnoreCase("df")) {
    fsInfo();
  } 
  else if (action.equalsIgnoreCase("format")) {
    if (args.equalsIgnoreCase("yes")) {
      fsFormat();
    } else {
      Serial.println("Warning: To format flash, type: 'format yes'");
    }
  }

  // 4. Sensor Serial Bridge Commands
  else if (action.equalsIgnoreCase("sensor")) {
    if (args.startsWith("send ")) {
      String sText = args.substring(5);
      SENSOR_SERIAL.println(sText);
      Serial.printf("[Sensor] Sent (%d bytes): %s\n", sText.length(), sText.c_str());
    } else if (args.equalsIgnoreCase("flush")) {
      while (SENSOR_SERIAL.available()) SENSOR_SERIAL.read();
      Serial.println("[Sensor] Buffer flushed.");
    } else {
      Serial.println("Usage: sensor send <data> | sensor flush");
    }
  }

  // 5. System Commands
  else if (action.equalsIgnoreCase("reboot")) {
    Serial.println("[System] Rebooting ESP32...");
    delay(500);
    ESP.restart();
  } 
  else {
    Serial.printf("Unknown command '%s'. Type 'help' or 'menu'.\n", action.c_str());
  }
}

// ==============================================================================
// SETUP (Boot Sequence: Wi-Fi First -> Website Registration Second)
// ==============================================================================
void setup() {
  pinMode(PIN_LED, OUTPUT);
  digitalWrite(PIN_LED, LED_ACTIVE_LOW ? HIGH : LOW);

  // Initialize Primary USB CLI Serial
  Serial.begin(CLI_BAUD);
  Serial.setTimeout(50);
  delay(1200);

  Serial.println("\n\n===============================================================");
  Serial.printf ("  ESP32 SENSOR PLATFORM & STORAGE DEVICE INITIALIZING\n");
  Serial.printf ("  Target Board Architecture : %s\n", BOARD_NAME);
  Serial.printf ("  Firmware Version          : %s\n", FIRMWARE_VERSION);
  Serial.println("===============================================================");

  // 1. Initialize Hardware Serial for External Sensor
  SENSOR_SERIAL.begin(SENSOR_BAUD, SERIAL_8N1, PIN_SENSOR_RX, PIN_SENSOR_TX);
  Serial.printf("[Sensor] Sensor Serial initialized on RX (Pin %d), TX (Pin %d) at %d baud.\n", 
                PIN_SENSOR_RX, PIN_SENSOR_TX, SENSOR_BAUD);

  // 2. Initialize LittleFS Flash Storage
  fsBegin();

  // 3. STEP ONE: WI-FI SETUP & INITIALIZATION
  Serial.println("\n--- STEP 1: WI-FI INITIALIZATION ---");
  wifiInit();

  // If Wi-Fi did not auto-connect from saved credentials, trigger interactive wizard
  if (!wifiIsConnected()) {
    Serial.println("[WiFi] Starting interactive scan so you can select your network...");
    int found = wifiScan();
    if (found > 0) {
      Serial.println("Enter network number to connect (or 'cancel'):");
      Serial.print("Select network [1-");
      Serial.print(found);
      Serial.print("]: ");
      cliState = CLI_STATE_WAIT_SSID_SELECT;
    }
  }

  // 4. STEP TWO: PLATFORM INITIALIZATION & REGISTRATION
  Serial.println("\n--- STEP 2: SENSOR PLATFORM & REGISTRATION ---");
  platformInit();

  if (wifiIsConnected()) {
    if (!platformIsRegistered()) {
      Serial.println("[Platform] Device has no token stored. Registering with website...");
      platformRegister();
    } else {
      Serial.println("[Platform] Registered token found. Synchronizing initial heartbeat with website...");
      platformHeartbeat();
    }
  } else {
    Serial.println("[Platform] Waiting for Wi-Fi connection before syncing with server...");
  }

  // 5. Present Main Menu
  printMenu();
  if (cliState == CLI_STATE_NORMAL) {
    Serial.print("esp32> ");
  }
}

// ==============================================================================
// MAIN LOOP
// ==============================================================================
void loop() {
  unsigned long currentMillis = millis();

  // Non-blocking Heartbeat Blink
  if (currentMillis - prevBlinkMillis >= blinkInterval) {
    prevBlinkMillis = currentMillis;
    ledState = !ledState;
    if (LED_ACTIVE_LOW) {
      digitalWrite(PIN_LED, ledState ? LOW : HIGH);
    } else {
      digitalWrite(PIN_LED, ledState ? HIGH : LOW);
    }
  }

  // Process incoming CLI characters from PC USB Serial
  while (Serial.available()) {
    char c = (char)Serial.read();
    if (c == '\r') continue;
    if (c == '\n') {
      Serial.println();
      parseCommand(inputBuffer);
      inputBuffer = "";
      if (cliState == CLI_STATE_NORMAL) {
        Serial.print("esp32> ");
      }
    } else if (c == '\b' || (int)c == 127) {
      if (inputBuffer.length() > 0) {
        inputBuffer.remove(inputBuffer.length() - 1);
        Serial.print("\b \b");
      }
    } else {
      inputBuffer += c;
      if (cliState == CLI_STATE_WAIT_PASSWORD) {
        Serial.print('*');
      } else {
        Serial.print(c);
      }
    }
  }

  // Monitor incoming external sensor data and echo to CLI
  if (SENSOR_SERIAL.available()) {
    Serial.print("\n[Sensor-IN]: ");
    while (SENSOR_SERIAL.available()) {
      Serial.write(SENSOR_SERIAL.read());
    }
    Serial.println();
    if (cliState == CLI_STATE_NORMAL) {
      Serial.print("esp32> ");
    }
  }
}
