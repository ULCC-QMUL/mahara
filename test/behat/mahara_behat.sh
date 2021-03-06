#!/bin/bash

# Get action and Mahara dir
ACTION=$1
REPORT=$3
SCRIPTPATH=`readlink -f "${BASH_SOURCE[0]}"`
MAHARAROOT=`dirname $( dirname $( dirname "$SCRIPTPATH" ))`
BEHATROOT=`grep -v '^//\|^#' $MAHARAROOT/htdocs/config.php | grep behat_dataroot | grep -o "['\"].*['\"];" | sed "s/['\";]//g"`
SERVER=0
test -z $SELENIUM_PORT && export SELENIUM_PORT=4444
test -z $PHP_PORT && export PHP_PORT=8000
test -z $XVFB_PORT && export XVFB_PORT=10

echo "S: $SELENIUM_PORT"
echo "P: $PHP_PORT"

# Wait and check if the selenium server is running in maximum 15 seconds
function is_selenium_running {
    for i in `seq 1 15`; do
        sleep 1
        res=$(curl -o /dev/null --silent --write-out '%{http_code}\n' http://localhost:${SELENIUM_PORT}/wd/hub/status)
        if [ $res == "200" ]; then
            return 0;
        fi
    done
    return 1;
}

function cleanup {
    echo "Shutdown Selenium"
    curl -o /dev/null --silent http://localhost:${SELENIUM_PORT}/selenium-server/driver/?cmd=shutDownSeleniumServer

    if [[ $REPORT == 'html' ]]
    then
        xdg-open file://${BEHATROOT}/behat/html_results/index.html
    fi

    if [[ $SERVER ]]
    then
        echo "Shutdown PHP server"
        kill $SERVER
    fi

    if [[ $1 ]]
    then
        exit $1
    else
        exit 255
    fi

    echo "Disable behat test environment"
    php htdocs/testing/frameworks/behat/cli/util.php -d
}

# Check we are not running as root for some weird reason
if [[ "$USER" = "root" ]]
then
    echo "This script should not be run as root"
    exit 1
fi

cd $MAHARAROOT

# Trap errors so we can cleanup
trap cleanup ERR
trap cleanup INT

if [ "$ACTION" = "action" ]
then

    # Wrap the util.php script

    PERFORM=$2
    php htdocs/testing/frameworks/behat/cli/util.php --$PERFORM

elif [ "$ACTION" = "run" -o "$ACTION" = "runheadless" -o "$ACTION" = "rundebug" -o "$ACTION" = "runfresh" -o "$ACTION" = "rundebugheadless" ] && [ "$2" != "html" ]
then

    if [[ $2 == @* ]]; then
        TAGS=$2
        echo "Only run tests with the tag: $TAGS"
    elif [ $2 ]; then
        if [[ $2 == */* ]]; then
            FEATURE="test/behat/features/$2"
        else
            FEATURE=`find test/behat/features -name $2 | head -n 1`
        fi
        echo "Only run tests in file: $FEATURE"
    else
        echo "Run all tests"
    fi

    if [ "$ACTION" = "runfresh" ]
    then
        echo "Drop the old test site if exist"
        php htdocs/testing/frameworks/behat/cli/util.php --drop
    fi

    # Initialise the test site for behat (database, dataroot, behat yml config)
    php htdocs/testing/frameworks/behat/cli/init.php

    # Run the Behat tests themselves (after any intial setup)
    if is_selenium_running; then
        echo "Selenium is running"
    else
        echo "Start Selenium..."

        SELENIUM_VERSION_MAJOR=2.53
        SELENIUM_VERSION_MINOR=1

        SELENIUM_FILENAME=selenium-server-standalone-$SELENIUM_VERSION_MAJOR.$SELENIUM_VERSION_MINOR.jar
        SELENIUM_PATH=./test/behat/$SELENIUM_FILENAME
        # @todo make this more flexible, cross-platform?
        CHROMEDRIVER_PATH=./test/behat/chromedriver-2.35-linux64

        # If no Selenium installed, download it
        if [ ! -f $SELENIUM_PATH ]; then
            echo "Downloading Selenium..."
            wget -q -O $SELENIUM_PATH http://selenium-release.storage.googleapis.com/$SELENIUM_VERSION_MAJOR/$SELENIUM_FILENAME
            echo "Downloaded"
        fi

        if [ $ACTION = 'runheadless' -o $ACTION = 'rundebugheadless' ]
        then
            # we want to run selenium headless on a different display - this allows for that ;)
            echo "Starting Xvfb ..."
            Xvfb :${XVFB_PORT} -ac > /tmp/xvfb-${XVFB_PORT}.log 2>&1 & echo "PID [$!]"
            DISPLAY=:${XVFB_PORT} nohup java -Dwebdriver.chrome.driver=$CHROMEDRIVER_PATH -jar $SELENIUM_PATH -port ${SELENIUM_PORT} -log /tmp/selenium-${SELENIUM_PORT}.log > /tmp/selenium-${SELENIUM_PORT}.log 2>&1 & echo $!
        else
            java -Dwebdriver.chrome.driver=$CHROMEDRIVER_PATH -jar $SELENIUM_PATH -port ${SELENIUM_PORT} -log /tmp/selenium-${SELENIUM_PORT}.log > /tmp/selenium-${SELENIUM_PORT}.log 2>&1 &
        fi

        if is_selenium_running; then
            echo "Selenium started"
        else
            echo "Selenium can't be started"
            exit 1
        fi
    fi

    echo "Start PHP server"
    php --server localhost:${PHP_PORT} --docroot $MAHARAROOT/htdocs > /tmp/php-${PHP_PORT}.log 2>&1 &
    SERVER=$!

    BEHATCONFIGFILE=`php htdocs/testing/frameworks/behat/cli/util.php --config`
    echo "Run Behat..."

    #added html format for html report
    OPTIONS=''
    if [[ $REPORT == 'html' ]]
      then
      if [ "$ACTION" = "rundebug" -o "$ACTION" = "rundebugheadless" ]
      then
          OPTIONS=$OPTIONS" --format=pretty --format=html"
      else
          OPTIONS=$OPTIONS" --format=progress --format=html"
      fi
    elif [ "$ACTION" = "rundebug" -o "$ACTION" = "rundebugheadless" ]
    then
          OPTIONS=$OPTIONS" --format=pretty"
    fi

    if [ "$TAGS" ]; then
        OPTIONS=$OPTIONS" --tags "$TAGS
    elif [ "$FEATURE" ]; then
        OPTIONS=$OPTIONS" "$FEATURE
    fi

    echo
    echo "=================================================="
    echo

    echo ./external/vendor/bin/behat --config $BEHATCONFIGFILE $OPTIONS
    ./external/vendor/bin/behat --config $BEHATCONFIGFILE $OPTIONS

    echo
    echo "=================================================="
    echo
    echo "Shutdown"
    cleanup 0
else
    # Help text if we got an unexpected (or empty) first param
    echo "Expected something like one of the following:"
    echo
    echo "# Run all tests:"
    echo "mahara_behat run"
    echo ""
    echo "# Run tests in file \"example.feature\""
    echo "mahara_behat run example.feature"
    echo ""
    echo "# Run tests with specific tag:"
    echo "mahara_behat run @tagname"
    echo ""
    echo "# Run tests with extra debug output:"
    echo "mahara_behat rundebug"
    echo "mahara_behat rundebug example.feature"
    echo "mahara_behat rundebug @tagname"
    echo ""
    echo "# Run in headless mode (requires xvfb):"
    echo "mahara_behat runheadless"
    echo ""
    echo "# Run in headless mode with extra debug output:"
    echo "mahara_behat rundebugheadless"
    echo ""
    echo "# To run html report option, add html as the third command line argument."
    echo "# Ex 1. To run selected tests:"
    echo "mahara_behat runheadless example.feature html"
    echo "# Ex 2. To run the whole suite:"
    echo "mahara_behat runheadless null html"
    echo "# If running linux, the report will open automatically, otherwise you'll find it in your behat dataroot"
    echo ""
    echo "# Enable test site:"
    echo "mahara_behat action enable"
    echo ""
    echo "# Disable test site:"
    echo "mahara_behat action disable"
    echo ""
    echo "# List other actions you can perform:"
    echo "mahara_behat action help"
    exit 1
fi
