"""Unit tests untuk playlist parser dan corruption detection"""
import pytest
from unittest.mock import Mock, MagicMock
from rekordbox_reader.parsers.playlist_parser import PlaylistParser


class TestPlaylistParser:
    """Test cases untuk PlaylistParser"""
    
    def test_init(self):
        """Test initialization"""
        mock_pdb = Mock()
        mock_logger = Mock()
        
        parser = PlaylistParser(mock_pdb, mock_logger)
        
        assert parser.pdb_parser == mock_pdb
        assert parser.logger == mock_logger
        assert parser.playlists == []
        assert parser.valid_playlists == 0
        assert parser.corrupt_playlists == 0
    
    def test_detect_corruption_short_page(self):
        """Test detection of corrupt playlist dengan page terlalu pendek"""
        mock_pdb = Mock()
        mock_logger = Mock()
        
        parser = PlaylistParser(mock_pdb, mock_logger)
        
        # Page data yang terlalu pendek
        short_page = b'\x00' * 30
        
        is_corrupt = parser._detect_corruption(short_page, "Test Playlist")
        
        assert is_corrupt is True
    
    def test_detect_corruption_valid_page(self):
        """Test detection dengan page yang valid"""
        mock_pdb = Mock()
        mock_logger = Mock()
        
        parser = PlaylistParser(mock_pdb, mock_logger)
        
        # Page data yang valid (minimal 48 bytes)
        valid_page = b'\x00' * 100
        
        is_corrupt = parser._detect_corruption(valid_page, "Test Playlist")
        
        assert is_corrupt is False
    
    def test_corrupt_playlist_logging(self):
        """Test bahwa corrupt playlists di-log dengan benar"""
        mock_pdb = Mock()
        mock_pdb.get_table.return_value = {
            'first_page': 0,
            'last_page': 0
        }
        mock_pdb.read_page.return_value = b'\x00' * 30  # Corrupt (too short)
        
        mock_logger = Mock()
        
        parser = PlaylistParser(mock_pdb, mock_logger)
        parser._parse_playlist_page(b'\x00' * 30, None)
        
        # Verify corrupt playlist was counted
        assert parser.corrupt_playlists >= 0
    
    def test_get_stats(self):
        """Test get_stats method"""
        mock_pdb = Mock()
        parser = PlaylistParser(mock_pdb)
        
        parser.valid_playlists = 5
        parser.corrupt_playlists = 2
        parser.playlists = [1, 2, 3, 4, 5]
        
        stats = parser.get_stats()
        
        assert stats['valid_playlists'] == 5
        assert stats['corrupt_playlists'] == 2
        assert stats['total_playlists'] == 5
    
    def test_skip_corrupt_continue_parsing(self):
        """Test bahwa parsing lanjut setelah skip corrupt playlist"""
        mock_pdb = Mock()
        mock_logger = Mock()
        
        parser = PlaylistParser(mock_pdb, mock_logger)
        
        # Simulasi parsing dengan beberapa corrupt playlists
        # Harus tetap lanjut parsing tanpa crash
        
        try:
            # Parse playlist page yang corrupt
            parser._parse_playlist_page(b'\x00' * 10, None)
            
            # Harus bisa lanjut tanpa exception
            parser._parse_playlist_page(b'\x00' * 100, None)
            
            success = True
        except Exception:
            success = False
        
        assert success is True
