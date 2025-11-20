"""Logging system untuk Rekordbox Export Reader"""
import logging
import json
from datetime import datetime
from pathlib import Path
from colorama import Fore, Style, init

init(autoreset=True)

class RekordboxLogger:
    """Logger dengan support untuk tracking corrupt playlists"""
    
    def __init__(self, log_dir="output", verbose=False):
        self.log_dir = Path(log_dir)
        self.log_dir.mkdir(exist_ok=True)
        
        self.corrupt_playlists = []
        self.warnings = []
        self.errors = []
        
        # Setup logger
        self.logger = logging.getLogger('RekordboxReader')
        self.logger.setLevel(logging.DEBUG if verbose else logging.INFO)
        
        # Console handler dengan warna
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.DEBUG if verbose else logging.INFO)
        console_formatter = ColoredFormatter(
            '%(levelname)s - %(message)s'
        )
        console_handler.setFormatter(console_formatter)
        self.logger.addHandler(console_handler)
        
        # File handler
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        file_handler = logging.FileHandler(
            self.log_dir / f'rekordbox_reader_{timestamp}.log'
        )
        file_handler.setLevel(logging.DEBUG)
        file_formatter = logging.Formatter(
            '%(asctime)s - %(levelname)s - %(message)s'
        )
        file_handler.setFormatter(file_formatter)
        self.logger.addHandler(file_handler)
    
    def info(self, message):
        self.logger.info(message)
    
    def debug(self, message):
        self.logger.debug(message)
    
    def warning(self, message):
        self.warnings.append(message)
        self.logger.warning(message)
    
    def error(self, message):
        self.errors.append(message)
        self.logger.error(message)
    
    def log_corrupt_playlist(self, playlist_name, reason, details=None):
        """Log playlist yang corrupt"""
        corrupt_entry = {
            'playlist_name': playlist_name,
            'reason': reason,
            'details': details,
            'timestamp': datetime.now().isoformat()
        }
        self.corrupt_playlists.append(corrupt_entry)
        self.warning(f"Playlist corrupt dilewati: {playlist_name} - {reason}")
    
    def save_corrupt_playlist_log(self):
        """Simpan daftar corrupt playlists ke file JSON"""
        if self.corrupt_playlists:
            log_file = self.log_dir / 'corrupt_playlists.json'
            with open(log_file, 'w', encoding='utf-8') as f:
                json.dump(self.corrupt_playlists, f, indent=2, ensure_ascii=False)
            self.info(f"Daftar corrupt playlists disimpan ke: {log_file}")
    
    def get_stats(self):
        """Dapatkan statistik logging"""
        return {
            'corrupt_playlists': len(self.corrupt_playlists),
            'warnings': len(self.warnings),
            'errors': len(self.errors)
        }


class ColoredFormatter(logging.Formatter):
    """Formatter dengan warna untuk output console"""
    
    COLORS = {
        'DEBUG': Fore.CYAN,
        'INFO': Fore.GREEN,
        'WARNING': Fore.YELLOW,
        'ERROR': Fore.RED,
        'CRITICAL': Fore.RED + Style.BRIGHT
    }
    
    def format(self, record):
        levelname = record.levelname
        if levelname in self.COLORS:
            record.levelname = f"{self.COLORS[levelname]}{levelname}{Style.RESET_ALL}"
        return super().format(record)
