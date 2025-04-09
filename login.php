<?php
class DataBuffer {
    private $localFile = 'data_buffer.json';
    private $maxBufferSize = 1000;
    private $con = null;

    public function __construct() {
        $this->initializeDatabaseConnection();
    }

    private function initializeDatabaseConnection() {
        $this->con = @mysqli_connect("localhost", "root", "", "moodtoons");
    }

    public function insertUserData($email, $password) {
        // Sanitize inputs
        $email = $this->sanitizeInput($email);
        $password = $this->sanitizeInput($password);

        // Try direct insertion first
        if ($this->isServerOnline()) {
            return $this->insertToDatabase($email, $password);
        }
        
        // Fall back to buffering
        return $this->bufferDataLocally($email, $password);
    }

    private function isServerOnline() {
        if (!$this->con) {
            $this->initializeDatabaseConnection();
        }
        return $this->con !== false;
    }

    private function insertToDatabase($email, $password) {
        $sql = "INSERT INTO users (email, password) VALUES ('$email', '$password')";
        $result = mysqli_query($this->con, $sql);

        if ($result) {
            $this->processBufferedData();
            return true;
        } else {
            // If insertion fails, buffer the data
            return $this->bufferDataLocally($email, $password);
        }
    }

    private function bufferDataLocally($email, $password) {
        $bufferedData = [];
        if (file_exists($this->localFile)) {
            $bufferedData = json_decode(file_get_contents($this->localFile), true);
            if (!is_array($bufferedData)) {
                $bufferedData = [];
            }
        }

        $bufferedData[] = [
            'email' => $email,
            'password' => $password,
            'timestamp' => time()
        ];

        // Save to file
        $result = file_put_contents($this->localFile, json_encode($bufferedData));

        // If buffer is getting large, try to process it
        if (count($bufferedData) > $this->maxBufferSize) {
            $this->processBufferedData(true);
        }

        return $result !== false;
    }

    private function processBufferedData($force = false) {
        if (!file_exists($this->localFile)) return true;

        $bufferedData = json_decode(file_get_contents($this->localFile), true);
        if (empty($bufferedData)) return true;

        // Only proceed if server is online or we're forcing
        if (!$force && !$this->isServerOnline()) return false;

        $successCount = 0;
        $remainingData = [];

        foreach ($bufferedData as $record) {
            if ($this->insertToDatabase($record['email'], $record['password'])) {
                $successCount++;
            } else {
                $remainingData[] = $record;
            }
        }

        // Save back any remaining failed records
        file_put_contents($this->localFile, json_encode($remainingData));

        return $successCount > 0;
    }

    private function sanitizeInput($input) {
        if ($this->con) {
            return mysqli_real_escape_string($this->con, $input);
        }
        // Basic sanitization when DB connection isn't available
        return htmlspecialchars(strip_tags($input));
    }

    public function __destruct() {
        if ($this->con) {
            mysqli_close($this->con);
        }
    }
}

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $dataBuffer = new DataBuffer();
    $result = $dataBuffer->insertUserData($_POST['email'], $_POST['password']);

    if ($result) {
        header("Location: index.html");
        exit();
    } else {
        // Check if data was buffered
        if (file_exists('data_buffer.json')) {
            echo "Our servers are temporarily unavailable. Your data has been saved and will be processed as soon as we're back online. You can continue using the application.";
        } else {
            echo "Error: Unable to process your request. Please try again later.";
        }
    }
} else {
    header("Location: index.html");
    exit();
}
?>