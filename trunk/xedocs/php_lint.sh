#!/bin/sh

for i in $(ls *.php) 
do 
	php -l $i | grep -v 'No syntax errors detected'
done

