#!/bin/sh
export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/sd/lib:/sd/usr/lib
export PATH=$PATH:/sd/usr/bin:/sd/usr/sbin

[[ -f /tmp/PMKID.progress ]] && {
  exit 0
}

touch /tmp/PMKID.progress

HCXDUMPTOOL=https://github.com/adde88/hcxtools-hcxdumptool-openwrt/raw/b23ae47fa5a9c5d137a0627459cbf0f8a5a1ba4b/bin/ar71xx/packages/base/hcxdumptool_4.2.1-8_ar71xx.ipk
HCXTOOLS=https://github.com/adde88/hcxtools-hcxdumptool-openwrt/raw/cea232511fb6de3b4b71d3b07b8181bb55145a2b/bin/ar71xx/packages/base/hcxtools_4.2.1-9_ar71xx.ipk

if [ "$1" = "install" ]; then
  if [ "$2" = "internal" ]; then
    wget $HCXDUMPTOOL -O /tmp/hcxdumptool.ipk
    wget $HCXTOOLS -O /tmp/hcxtools.ipk
    opkg install /tmp/hcxdumptool.ipk
    opkg install /tmp/hcxtools.ipk
    rm /tmp/hcxdumptool.ipk
    rm /tmp/hcxtools.ipk
  elif [ "$2" = "sd" ]; then
    wget $HCXDUMPTOOL -O /tmp/hcxdumptool.ipk
    wget $HCXTOOLS -O /tmp/hcxtools.ipk
    opkg install /tmp/hcxdumptool.ipk --dest sd
    opkg install /tmp/hcxtools.ipk --dest sd
    rm /tmp/hcxdumptool.ipk
    rm /tmp/hcxtools.ipk
  fi

  touch /etc/config/pmkid
  echo "config pmkid 'module'" > /etc/config/pmkid

  uci set pmkid.module.installed=1
  uci commit pmkid.module.installed

elif [ "$1" = "remove" ]; then
    opkg remove hcxdumptool
    opkg remove hcxtools
fi

rm /tmp/PMKID.progress
