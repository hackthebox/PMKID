#!/bin/sh
#2015 - g0blin & Zylla

export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/sd/lib:/sd/usr/lib
export PATH=$PATH:/sd/usr/bin:/sd/usr/sbin

MYTIME=`date +%s`
LOG=/tmp/pmkid.log
LOCK=/tmp/pmkid.lock

MYMONITOR=''
MYINTERFACE=$2
INCLUDEOREXCLUDE=$3
case "$INCLUDEOREXCLUDE" in
  include)
    FILTERMODE=2
    ;;
  exclude)
    FILTERMODE=1
    ;;
  *)
    FILTERMODE=2
    ;;
esac
COMMANDLINEARGS=${*:4}

if [ "$1" = "start" ]; then

  killall -9 hcxdumptool &>/dev/null
  rm ${LOG} || true &>/dev/null
  rm ${LOCK} || true &>/dev/null

	echo -e "Starting Capture..." > ${LOG}

	if [ -z "$MYINTERFACE" ]; then
		MYINTERFACE=`iwconfig 2> /dev/null | grep "Mode:Master" | awk '{print $1}' | head -1`
	else
		MYFLAG=`iwconfig 2> /dev/null | awk '{print $1}' | grep ${MYINTERFACE}`

		if [ -z "$MYFLAG" ]; then
		    MYINTERFACE=`iwconfig 2> /dev/null | grep "Mode:Master" | awk '{print $1}' | head -1`
		fi
	fi

	if [ -z "$MYMONITOR" ]; then
		MYMONITOR=`iwconfig 2> /dev/null | grep "Mode:Monitor" | awk '{print $1}' | grep ${MYINTERFACE}`

		MYFLAG=`iwconfig 2> /dev/null | awk '{print $1}' | grep ${MYMONITOR}`

		if [ -z "$MYFLAG" ]; then
		    airmon-ng start ${MYINTERFACE}
		    MYMONITOR=`iwconfig 2> /dev/null | grep "Mode:Monitor" | awk '{print $1}' | grep ${MYINTERFACE}`
		fi
	else
		MYFLAG=`iwconfig 2> /dev/null | awk '{print $1}' | grep ${MYMONITOR}`

		if [ -z "$MYFLAG" ]; then
		    airmon-ng start ${MYINTERFACE}
		    MYMONITOR=`iwconfig 2> /dev/null | grep "Mode:Monitor" | awk '{print $1}' | grep ${MYINTERFACE}`
		fi
	fi

	echo -e "Monitor : ${MYMONITOR}" >> ${LOG}

  if [ ! -d /pineapple/modules/PMKID/capture ]; then
    mkdir /pineapple/modules/PMKID/capture
  fi

  echo $MYTIME > ${LOCK}

  hcxdumptool -o /pineapple/modules/PMKID/capture/capture_${MYTIME} -i ${MYMONITOR} $COMMANDLINEARGS --filtermode=$FILTERMODE --filterlist_ap=/tmp/pmkid-selectedAps &> ${LOG} &

elif [ "$1" = "stop" ]; then
  killall -9 hcxdumptool
  cat ${LOG} | grep -v '$HEX' > /pineapple/modules/PMKID/capture/capture_$(cat ${LOCK}).log
  cp /tmp/pmkid-aps /pineapple/modules/PMKID/capture/capture_$(cat ${LOCK}).aps
  cp /tmp/pmkid-selectedAps /pineapple/modules/PMKID/capture/capture_$(cat ${LOCK}).selectedAps
  rm ${LOCK}
fi
