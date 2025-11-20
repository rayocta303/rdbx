"""Parser untuk playlist data dengan deteksi corruption"""
from typing import Dict, List, Optional


class PlaylistParser:
    """Parser untuk playlist dengan corruption detection"""
    
    def __init__(self, pdb_parser, logger=None):
        self.pdb_parser = pdb_parser
        self.logger = logger
        self.playlists = []
        self.valid_playlists = 0
        self.corrupt_playlists = 0
    
    def parse_playlists(self) -> List[Dict]:
        """Parse semua playlists dengan corruption handling"""
        playlist_tree = self.pdb_parser.get_table(self.pdb_parser.TABLE_PLAYLIST_TREE)
        playlist_entries = self.pdb_parser.get_table(self.pdb_parser.TABLE_PLAYLIST_ENTRIES)
        
        if not playlist_tree:
            if self.logger:
                self.logger.warning("Playlist tree table not found")
            return []
        
        if self.logger:
            self.logger.info("Parsing playlists dari database...")
        
        # Parse playlist tree
        playlists = self._extract_playlist_tree(playlist_tree, playlist_entries)
        
        if self.logger:
            self.logger.info(
                f"Playlist parsing selesai: {self.valid_playlists} valid, "
                f"{self.corrupt_playlists} corrupt (dilewati)"
            )
        
        return playlists
    
    def _extract_playlist_tree(self, tree_table: Dict, entries_table: Optional[Dict]) -> List[Dict]:
        """Extract playlist tree structure"""
        playlists = []
        
        first_page = tree_table['first_page']
        last_page = tree_table['last_page']
        
        for page_idx in range(first_page, last_page + 1):
            page_data = self.pdb_parser.read_page(page_idx)
            if not page_data:
                continue
            
            # Parse playlist nodes dari page
            page_playlists = self._parse_playlist_page(page_data, entries_table)
            playlists.extend(page_playlists)
        
        return playlists
    
    def _parse_playlist_page(self, page_data: bytes, entries_table: Optional[Dict]) -> List[Dict]:
        """Parse playlist nodes dari single page dengan corruption detection"""
        playlists = []
        
        try:
            if len(page_data) < 48:
                return playlists
            
            # Simplified playlist parsing
            # Real implementation would parse:
            # - playlist_id, parent_id, name
            # - is_folder, sort_order
            # - entry count
            
            playlist_name = "Sample Playlist"
            
            # Simulasi corruption detection
            is_corrupt = self._detect_corruption(page_data, playlist_name)
            
            if is_corrupt:
                self.corrupt_playlists += 1
                if self.logger:
                    self.logger.log_corrupt_playlist(
                        playlist_name,
                        "Invalid playlist structure detected",
                        {"page_size": len(page_data)}
                    )
                return playlists
            
            # Get playlist entries
            entries = []
            if entries_table:
                entries = self._get_playlist_entries(1, entries_table)
            
            playlist = {
                'id': 1,
                'name': playlist_name,
                'parent_id': 0,
                'is_folder': False,
                'entries': entries,
                'track_count': len(entries)
            }
            
            playlists.append(playlist)
            self.valid_playlists += 1
            
        except Exception as e:
            self.corrupt_playlists += 1
            if self.logger:
                self.logger.log_corrupt_playlist(
                    "Unknown Playlist",
                    f"Parsing error: {str(e)}",
                    {"error_type": type(e).__name__}
                )
        
        return playlists
    
    def _detect_corruption(self, page_data: bytes, playlist_name: str) -> bool:
        """Detect playlist corruption berdasarkan berbagai indikator"""
        
        # Check 1: Page size minimum
        if len(page_data) < 48:
            return True
        
        # Check 2: Invalid offset markers
        # Simplified check - real implementation would check actual offsets
        
        # Check 3: Incomplete structure
        # Check for proper page header
        
        # Check 4: Invalid data patterns
        # Look for null bytes or invalid sequences
        
        # Untuk demonstrasi, return False (tidak corrupt)
        return False
    
    def _get_playlist_entries(self, playlist_id: int, entries_table: Dict) -> List[int]:
        """Get track IDs untuk specific playlist"""
        entries = []
        
        try:
            first_page = entries_table['first_page']
            last_page = entries_table['last_page']
            
            for page_idx in range(first_page, last_page + 1):
                page_data = self.pdb_parser.read_page(page_idx)
                if not page_data:
                    continue
                
                # Parse entries (simplified)
                # Real implementation would match playlist_id and extract track_ids
                
        except Exception as e:
            if self.logger:
                self.logger.debug(f"Error getting playlist entries: {e}")
        
        return entries
    
    def get_stats(self) -> Dict:
        """Get playlist parsing statistics"""
        return {
            'total_playlists': len(self.playlists),
            'valid_playlists': self.valid_playlists,
            'corrupt_playlists': self.corrupt_playlists
        }
