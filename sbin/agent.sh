#!/bin/sh

while [ $# -gt 0 ]
do
    CMD="$CMD $1"
    shift
done

eval "$CMD"
