#!/bin/sh

MYINTERFACE=$1
DURATION=$2
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
      echo $MYINTERFACE
      airmon-ng start ${MYINTERFACE}
      MYMONITOR=`iwconfig 2> /dev/null | grep "Mode:Monitor" | awk '{print $1}' | grep ${MYINTERFACE}`
  fi
fi

rm /tmp/pmkid-scan* /tmp/pmkid-aps 2>/dev/null || true
airodump-ng ${MYMONITOR} -w /tmp/pmkid-scan --write-interval 1 -o csv &>/dev/null &
PID=$!
sleep $DURATION
kill -9 $PID

SEEN_BSSID=false

while IFS=, read -r bssid first_seen last_seen channel speed privacy cipher authentication power beacons iv lan_ip id_length essid key
do
    if [ "$bssid" = "BSSID" ]; then
      SEEN_BSSID=true
      continue
    fi
    if [ "$bssid" = "Station MAC" ]; then
      break
    fi
    if [ "$SEEN_BSSID" = true ]; then
      if [ ! -z "$(echo $essid | tr -d '[:space:]')" ]; then
        echo "$bssid,${essid:1}"
      fi
    fi
done < /tmp/pmkid-scan-01.csv > /tmp/pmkid-aps
