#!/bin/sh
export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/sd/lib:/sd/usr/lib
export PATH=$PATH:/sd/usr/bin:/sd/usr/sbin

[[ -f /tmp/PMKID.progress ]] && {
  exit 0
}

touch /tmp/PMKID.progress
mkdir -p /tmp/PMKID
wget https://github.com/adde88/hcxtools-hcxdumptool-openwrt/tree/openwrt-19.07-mk6/bin/packages/mips_24kc/custom -P /tmp/PMKID
HCXDUMPTOOL=`grep -F "hcxdumptool-custom_" /tmp/PMKID/custom | awk {'print $8'} | awk -F'"' {'print $2'}`
HCXTOOLS=`grep -F "hcxtools-custom_" /tmp/PMKID/custom | awk {'print $8'} | awk -F'"' {'print $2'}`

if [ "$1" = "install" ]; then
  if [ "$2" = "internal" ]; then
    wget https://github.com/adde88/hcxtools-hcxdumptool-openwrt/raw/openwrt-19.07-mk6/bin/packages/mips_24kc/custom/"$HCXDUMPTOOL" -P /tmp/PMKID
    wget https://github.com/adde88/hcxtools-hcxdumptool-openwrt/raw/openwrt-19.07-mk6/bin/packages/mips_24kc/custom/"$HCXTOOLS" -P /tmp/PMKID
    opkg install /tmp/PMKID/*.ipk
  elif [ "$2" = "sd" ]; then
    wget https://github.com/adde88/hcxtools-hcxdumptool-openwrt/raw/openwrt-19.07-mk6/bin/packages/mips_24kc/custom/"$HCXDUMPTOOL" -P /tmp/PMKID
    wget https://github.com/adde88/hcxtools-hcxdumptool-openwrt/raw/openwrt-19.07-mk6/bin/packages/mips_24kc/custom/"$HCXTOOLS" -P /tmp/PMKID
    opkg install /tmp/PMKID/*.ipk --dest sd
  fi

  touch /etc/config/pmkid
  echo "config pmkid 'module'" > /etc/config/pmkid

  uci set pmkid.module.commandLineArguments='--enable_status 3'
  uci commit pmkid.module.commandLineArguments
  uci set pmkid.module.includeOrExclude='include'
  uci commit pmkid.module.includeOrExclude
  uci set pmkid.module.installed=1
  uci commit pmkid.module.installed

elif [ "$1" = "remove" ]; then
    opkg remove hcxdumptool
    opkg remove hcxtools
fi

rm /tmp/PMKID.progress
rm -rf /tmp/PMKID
