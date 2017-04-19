#!/bin/sh
# emoncms updater script

EMONCMS_DIR=/var/www/html/emoncms
printf "\nUpdate emoncms....\n"
git -C $EMONCMS_DIR pull
for M in $EMONCMS_DIR/Modules/*
  do
    if [ -d "$M/.git" ]; then
      printf "\nUpdate emoncms/$(basename $M)....\n"
      git -C $M pull
    fi
  done
