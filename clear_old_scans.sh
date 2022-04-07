#!/bin/bash

find /opt/data1/ -mtime +30 \( -name "*.jpg" -o -name "*.jpg.*" -o -name "*.jpeg" -o -name "*.png" -o -name "*.pdf" \) -exec rm -f {} \;
find /opt/data1/ -depth -type d -empty -delete

