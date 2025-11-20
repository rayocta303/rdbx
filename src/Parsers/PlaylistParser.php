<?php

namespace RekordboxReader\Parsers;

class PlaylistParser {
    private $pdbParser;
    private $logger;
    private $playlists;
    private $validPlaylists;
    private $corruptPlaylists;

    public function __construct($pdbParser, $logger = null) {
        $this->pdbParser = $pdbParser;
        $this->logger = $logger;
        $this->playlists = [];
        $this->validPlaylists = 0;
        $this->corruptPlaylists = 0;
    }

    public function parsePlaylists() {
        $playlistTree = $this->pdbParser->getTable(PdbParser::TABLE_PLAYLIST_TREE);
        $playlistEntries = $this->pdbParser->getTable(PdbParser::TABLE_PLAYLIST_ENTRIES);

        if (!$playlistTree) {
            if ($this->logger) {
                $this->logger->warning("Playlist tree table not found");
            }
            return [];
        }

        if ($this->logger) {
            $this->logger->info("Parsing playlists dari database...");
        }

        $this->playlists = $this->extractPlaylistTree($playlistTree, $playlistEntries);

        if ($this->logger) {
            $this->logger->info(
                "Playlist parsing selesai: {$this->validPlaylists} valid, " .
                "{$this->corruptPlaylists} corrupt (dilewati)"
            );
        }

        return $this->playlists;
    }

    private function extractPlaylistTree($treeTable, $entriesTable) {
        $playlists = [];
        
        $firstPage = $treeTable['first_page'];
        $lastPage = $treeTable['last_page'];

        for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
            $pageData = $this->pdbParser->readPage($pageIdx);
            if (!$pageData) {
                continue;
            }

            $pagePlaylists = $this->parsePlaylistPage($pageData, $entriesTable, $pageIdx);
            $playlists = array_merge($playlists, $pagePlaylists);
        }

        return $playlists;
    }

    private function parsePlaylistPage($pageData, $entriesTable, $pageIdx) {
        $playlists = [];

        try {
            if (strlen($pageData) < 48) {
                return $playlists;
            }

            $pageHeader = unpack(
                'Vpage_index/' .
                'Vtype/' .
                'Vnext_page/' .
                'Vunknown1/' .
                'Vunknown2/' .
                'Cnum_rows_small/' .
                'Cu3/' .
                'Cu4/' .
                'Cpage_flags',
                substr($pageData, 0, 27)
            );

            $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
            
            if (!$isDataPage) {
                return $playlists;
            }

            $pageSize = strlen($pageData);
            $numRowsSmall = $pageHeader['num_rows_small'];

            for ($i = 0; $i < $numRowsSmall; $i++) {
                $rowIndexOffset = $pageSize - (($i + 1) * 2);
                if ($rowIndexOffset < 0) break;

                $rowOffsetData = unpack('v', substr($pageData, $rowIndexOffset, 2));
                $rowOffset = $rowOffsetData[1];

                $rowPresent = ($rowOffset & 0x8000) != 0;
                
                if (!$rowPresent) {
                    continue;
                }

                $actualRowOffset = ($rowOffset & 0x1FFF);
                
                try {
                    $playlist = $this->parsePlaylistRow($pageData, $actualRowOffset, $entriesTable);
                    if ($playlist) {
                        $playlists[] = $playlist;
                        $this->validPlaylists++;
                    }
                } catch (\Exception $e) {
                    $this->corruptPlaylists++;
                    if ($this->logger) {
                        $this->logger->warning("Corrupt playlist detected at page {$pageIdx}, row {$i}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            $this->corruptPlaylists++;
            if ($this->logger) {
                $this->logger->warning("Error parsing playlist page {$pageIdx}: " . $e->getMessage());
            }
        }

        return $playlists;
    }

    private function parsePlaylistRow($pageData, $offset, $entriesTable) {
        if ($offset + 20 > strlen($pageData)) {
            return null;
        }

        $playlistData = unpack(
            'vid/' .
            'vunknown1/' .
            'vunknown2/' .
            'vis_folder/' .
            'vunknown3',
            substr($pageData, $offset, 10)
        );

        $nameOffsetData = unpack('v', substr($pageData, $offset + 10, 2));
        $nameOffset = $nameOffsetData[1];

        $name = '';
        if ($nameOffset > 0 && ($offset + $nameOffset) < strlen($pageData)) {
            list($name, $newOffset) = $this->pdbParser->extractString($pageData, $offset + $nameOffset);
        }

        $parentIdData = unpack('V', substr($pageData, $offset + 12, 4));
        $parentId = $parentIdData[1];

        $entries = [];
        if ($entriesTable) {
            $entries = $this->getPlaylistEntries($playlistData['id'], $entriesTable);
        }

        return [
            'id' => $playlistData['id'],
            'name' => $name ?: 'Unnamed Playlist',
            'parent_id' => $parentId,
            'is_folder' => $playlistData['is_folder'] == 1,
            'entries' => $entries,
            'track_count' => count($entries)
        ];
    }

    private function getPlaylistEntries($playlistId, $entriesTable) {
        $entries = [];

        try {
            $firstPage = $entriesTable['first_page'];
            $lastPage = $entriesTable['last_page'];

            for ($pageIdx = $firstPage; $pageIdx <= $lastPage; $pageIdx++) {
                $pageData = $this->pdbParser->readPage($pageIdx);
                if (!$pageData) {
                    continue;
                }

                $pageEntries = $this->parseEntriesPage($pageData, $playlistId);
                $entries = array_merge($entries, $pageEntries);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->debug("Error getting playlist entries: " . $e->getMessage());
            }
        }

        return $entries;
    }

    private function parseEntriesPage($pageData, $targetPlaylistId) {
        $entries = [];

        if (strlen($pageData) < 48) {
            return $entries;
        }

        $pageHeader = unpack('Cpage_flags', substr($pageData, 27, 1));
        $isDataPage = ($pageHeader['page_flags'] & 0x40) == 0;
        
        if (!$isDataPage) {
            return $entries;
        }

        $pageSize = strlen($pageData);
        $numRowsData = unpack('Cnum_rows', substr($pageData, 24, 1));
        $numRows = $numRowsData['num_rows'];

        for ($i = 0; $i < $numRows; $i++) {
            $rowIndexOffset = $pageSize - (($i + 1) * 2);
            if ($rowIndexOffset < 0) break;

            $rowOffsetData = unpack('v', substr($pageData, $rowIndexOffset, 2));
            $rowOffset = $rowOffsetData[1];

            $rowPresent = ($rowOffset & 0x8000) != 0;
            if (!$rowPresent) continue;

            $actualRowOffset = ($rowOffset & 0x1FFF);

            if ($actualRowOffset + 8 > $pageSize) continue;

            $entryData = unpack(
                'ventry_id/' .
                'vtrack_id/' .
                'vplaylist_id/' .
                'vunknown',
                substr($pageData, $actualRowOffset, 8)
            );

            if ($entryData['playlist_id'] == $targetPlaylistId) {
                $entries[] = $entryData['track_id'];
            }
        }

        return $entries;
    }

    public function getStats() {
        return [
            'total_playlists' => count($this->playlists),
            'valid_playlists' => $this->validPlaylists,
            'corrupt_playlists' => $this->corruptPlaylists
        ];
    }

    public function getPlaylists() {
        return $this->playlists;
    }
}
