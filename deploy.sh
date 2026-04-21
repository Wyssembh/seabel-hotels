#!/bin/bash

FTP_HOST="ftpupload.net"
FTP_USER="if0_41594175"
FTP_PASS="Wissem0430a"
REMOTE_DIR="htdocs"
LOCAL_DIR="/home/wyssem/Bureau/rym_beach"

upload() {
    local local_path="$1"
    local remote_path="$2"
    echo "Upload: $remote_path"
    curl -s --ftp-create-dirs -T "$local_path" \
        "ftp://$FTP_HOST/$REMOTE_DIR/$remote_path" \
        --user "$FTP_USER:$FTP_PASS" 2>&1
}

upload_dir() {
    local dir="$1"
    local remote_base="$2"
    find "$dir" -type f | while read file; do
        relative="${file#$LOCAL_DIR/}"
        upload "$file" "$relative"
    done
}

echo "=== Déploiement Seabel Hotels sur InfinityFree ==="
upload_dir "$LOCAL_DIR" ""
echo "=== Déploiement terminé ! ==="
