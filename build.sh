#!/bin/sh
cd sgical
./build.sh > sgical.php
cd ..
php ./phpcompactor.php mka_ical.php complete.php 