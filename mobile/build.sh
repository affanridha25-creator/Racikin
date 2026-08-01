#!/bin/bash
# Build APK Racikin. Pakai: ./build.sh [debug|release]
set -e
export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home
export ANDROID_HOME="$HOME/Library/Android/sdk"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
DIR="$(cd "$(dirname "$0")" && pwd)"
echo "sdk.dir=$ANDROID_HOME" > "$DIR/android/local.properties"
cd "$DIR/android"
TASK="assembleDebug"; [ "$1" = "release" ] && TASK="assembleRelease bundleRelease"
./gradlew --no-daemon $TASK
