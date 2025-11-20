<?php

namespace RekordboxReader\Utils;

class Logger {
    private $logFile;
    private $verbose;
    private $corruptPlaylists;

    public function __construct($outputDir = 'output', $verbose = false) {
        $this->verbose = $verbose;
        $this->corruptPlaylists = [];
        
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $this->logFile = $outputDir . "/rekordbox_reader_{$timestamp}.log";
    }

    public function info($message) {
        $this->log('INFO', $message);
    }

    public function debug($message) {
        if ($this->verbose) {
            $this->log('DEBUG', $message);
        }
    }

    public function warning($message) {
        $this->log('WARNING', $message);
    }

    public function error($message) {
        $this->log('ERROR', $message);
    }

    private function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        
        if ($this->verbose || $level === 'ERROR' || $level === 'INFO') {
            echo $logMessage;
        }
    }

    public function logCorruptPlaylist($playlistName, $reason, $context = []) {
        $this->corruptPlaylists[] = [
            'playlist_name' => $playlistName,
            'reason' => $reason,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->warning("Corrupt playlist: {$playlistName} - {$reason}");
    }

    public function saveCorruptPlaylistLog() {
        if (empty($this->corruptPlaylists)) {
            return;
        }

        $outputFile = dirname($this->logFile) . '/corrupt_playlists.json';
        file_put_contents($outputFile, json_encode($this->corruptPlaylists, JSON_PRETTY_PRINT));
        
        $this->info("Corrupt playlist log saved to: {$outputFile}");
    }

    public function getCorruptPlaylists() {
        return $this->corruptPlaylists;
    }
}
