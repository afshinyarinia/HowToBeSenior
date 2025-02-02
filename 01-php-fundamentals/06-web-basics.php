<?php
// Start the session at the beginning
session_start();

// GET and POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Store in session
    $_SESSION['username'] = $username;
    
    // Set a cookie that expires in 1 hour
    setcookie('user_email', $email, time() + 3600);
}

// Session handling
$stored_username = $_SESSION['username'] ?? 'Guest';

// Cookie handling
$stored_email = $_COOKIE['user_email'] ?? 'No email stored';

?>

<!DOCTYPE html>
<html>
<head>
    <title>Web Basics in PHP</title>
</head>
<body>
    <h1>Web Forms Example</h1>
    
    <!-- GET method form -->
    <h2>Search (GET Method)</h2>
    <form method="get" action="">
        <input type="text" name="search" placeholder="Search...">
        <button type="submit">Search</button>
    </form>
    
    <?php
    if (isset($_GET['search'])) {
        echo "<p>You searched for: " . htmlspecialchars($_GET['search']) . "</p>";
    }
    ?>
    
    <!-- POST method form -->
    <h2>User Information (POST Method)</h2>
    <form method="post" action="">
        <div>
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <button type="submit">Submit</button>
    </form>
    
    <!-- Display stored information -->
    <h2>Stored Information</h2>
    <p>Session Username: <?php echo htmlspecialchars($stored_username); ?></p>
    <p>Cookie Email: <?php echo htmlspecialchars($stored_email); ?></p>
    
    <!-- File Upload Form -->
    <h2>File Upload</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <input type="file" name="uploaded_file">
        <button type="submit">Upload</button>
    </form>
    
    <?php
    // File upload handling
    if (isset($_FILES['uploaded_file'])) {
        $file = $_FILES['uploaded_file'];
        echo "<pre>";
        print_r($file);
        echo "</pre>";
    }
    
    // Display all server information
    echo "<h2>Server Information</h2>";
    echo "<p>Server IP: " . $_SERVER['SERVER_ADDR'] . "</p>";
    echo "<p>Request Method: " . $_SERVER['REQUEST_METHOD'] . "</p>";
    echo "<p>User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "</p>";
    ?>
</body>
</html> 