#!/bin/bash

# Source and destination directories
SRC="/home/www/bluedriver-live-data-plotter/"
DEST="/home/www/arsscriptum.github.io/bluedriver-live-data-plotter/"

# Default to no dry-run
DRYRUN=""

# Check for arguments
if [[ "$1" == "-d" || "$1" == "--dryrun" ]]; then
    DRYRUN="--dry-run"
    echo "Dry run enabled: showing what would be copied without making changes."
fi

# Run rsync
rsync -av $DRYRUN --exclude=".git" "$SRC" "$DEST"

# Final message
if [[ -n "$DRYRUN" ]]; then
    echo "Dry run complete: no files were copied."
else
    echo "Synchronization complete: $(date)"
fi
