#!/bin/bash
##
# Run any script as a test
##

# Fail faster
set -e

#Run rspec tests
rspec ../tests/rspec/test.rb

