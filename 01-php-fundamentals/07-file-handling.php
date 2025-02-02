<?php
/**
 * PHP File Handling
 * ----------------
 * This lesson covers:
 * 1. Reading files: fopen, fread, fgets, file_get_contents
 * 2. Writing files: fwrite, file_put_contents
 * 3. File system functions: copy, rename, unlink (delete)
 * 4. Directory handling: mkdir, rmdir, scandir
 * 5. File information: filesize, filetype, file_exists
 */

// Reading files
echo "=== Reading Files ===\n";

// Method 1: file_get_contents (simplest way to read entire file)
$content = file_get_contents(__FILE__);
echo "First 100 characters of this file:\n";
echo substr($content, 0, 100) . "\n\n";

// Method 2: fopen/fread (for large files or specific reading needs)
$handle = fopen(__FILE__, 'r');
$chunk = fread($handle, 50);
echo "First 50 characters using fread:\n$chunk\n\n";
fclose($handle);

// Writing files
echo "=== Writing Files ===\n";

// Method 1: file_put_contents (simple way to write)
$data = "Hello, World!\n";
file_put_contents('test.txt', $data);
echo "Written to test.txt using file_put_contents\n";

// Method 2: fopen/fwrite (more control over writing)
$handle = fopen('test2.txt', 'w');
fwrite($handle, "Line 1\n");
fwrite($handle, "Line 2\n");
fclose($handle);
echo "Written to test2.txt using fwrite\n\n";

// File system operations
echo "=== File System Operations ===\n";

// Copy file
copy('test.txt', 'test_backup.txt');
echo "File copied\n";

// Rename file
rename('test_backup.txt', 'test_new.txt');
echo "File renamed\n";

// Delete file
unlink('test_new.txt');
echo "File deleted\n\n";

// Directory handling
echo "=== Directory Handling ===\n";

// Create directory
if (!file_exists('test_dir')) {
    mkdir('test_dir');
    echo "Directory created\n";
}

// List directory contents
$files = scandir(__DIR__);
echo "Directory contents:\n";
print_r($files);

// Remove directory
rmdir('test_dir');
echo "Directory removed\n\n";

// File information
echo "=== File Information ===\n";
echo "File size: " . filesize(__FILE__) . " bytes\n";
echo "File type: " . filetype(__FILE__) . "\n";
echo "File exists: " . (file_exists(__FILE__) ? 'Yes' : 'No') . "\n";
echo "Is readable: " . (is_readable(__FILE__) ? 'Yes' : 'No') . "\n";
echo "Is writable: " . (is_writable(__FILE__) ? 'Yes' : 'No') . "\n";

// Clean up
if (file_exists('test.txt')) unlink('test.txt');
if (file_exists('test2.txt')) unlink('test2.txt'); 