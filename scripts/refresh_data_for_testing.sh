#!/bin/sh

THIS_DIR=$(dirname $0)
rm $THIS_DIR/../databases/campuses.db $THIS_DIR/../databases/events.db $THIS_DIR/../databases/reminders.db $THIS_DIR/../databases/updated_events_time.txt
