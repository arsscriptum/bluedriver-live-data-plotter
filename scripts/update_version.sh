#!/bin/bash

# Handy logging and error handling functions
pecho() { printf "%s\n" "$*"; }

log() { pecho "$@"; }

APPLICATION_TITLE="BlueDriver Live Statistics Viewer"
SCRIPT_PATH=$(realpath "$BASH_SOURCE")
SCRIPT_DIR=$(dirname "$SCRIPT_PATH")
BUILD_USER=$(whoami)
BUILD_TYPE="dev"
BUILD_DATE=$(date +"%Y-%m-%d %H:%M")



if [[ $BUILD_USER == "runner" ]]; then
    BUILD_TYPE="official"
fi



tmp_root=$(pushd "$SCRIPT_DIR/.." | awk '{print $1}')
ROOT_DIR=$(eval echo "$tmp_root")
# Path to output site.json
SITE_JSON="$ROOT_DIR/html/site.json"

LOGS_DIR="$ROOT_DIR/logs"
LOG_FILE="$LOGS_DIR/build.log"

VERSION_FILE=$ROOT_DIR/version.nfo
BUILD_FILE=$ROOT_DIR/build.nfo

# Get current version from version.nfo (assuming the format is major.minor.build)
current_version=$(cat "$VERSION_FILE")
IFS='.' read -r major minor build <<< "$current_version"

# Increment build number
build=$((build + 1))
new_version="$major.$minor.$build"

# Write the new version back to the version.nfo file
echo "$new_version" > "$VERSION_FILE"

# Get Git info
current_branch=$(git branch --show-current)
head_rev=$(git log --format=%h -1)
last_rev=$(git log --format=%h -2 | tail -n 1)

# Write the Git branch and revision information to build.nfo
{
    echo "$current_branch"
    echo "$head_rev"
} > "$BUILD_FILE"

log "Build date is $BUILD_DATE"
log "Build user is $BUILD_USER"
log "Build type is $BUILD_TYPE"
log "Version updated to $new_version"
log "Branch and revision info saved to $BUILD_FILE"

# Write JSON file
cat > "$SITE_JSON" <<EOF
{
  "Title": "$APPLICATION_TITLE",
  "Version": "$new_version",
  "Branch": "$current_branch",
  "Revision": "$head_rev-$BUILD_TYPE",
  "BuiltOn": "$BUILD_DATE"
}
EOF

log "Generated site.json at $SITE_JSON"
