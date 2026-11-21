#ifndef MY_FILE_H
#define MY_FILE_H

#include <Arduino.h>

// File System Lifecycle
bool fsBegin();                                                // Mount LittleFS (formats on first use if needed)
bool fsIsMounted();

// File & Directory Operations
void fsList(const char* path = "/");                          // ls [path]
void fsCat(const char* path);                                 // cat <file>
void fsWrite(const char* path, const char* text, bool append = false); // write / append
bool fsWriteBytes(const char* path, const uint8_t* data, size_t len);  // Write binary data
void fsRemove(const char* path);                              // rm <file>
void fsMkdir(const char* path);                               // mkdir <dir>
void fsRmdir(const char* path);                               // rmdir <dir> (must be empty)
void fsRename(const char* from, const char* to);              // mv <from> <to>
void fsInfo();                                                // df (disk free / storage info)
void fsFormat();                                              // format yes

// File Checks & Sizing
bool fsExists(const char* path);
size_t fsGetSize(const char* path);

#endif // MY_FILE_H
