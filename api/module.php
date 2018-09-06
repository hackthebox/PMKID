<?php
namespace pineapple;

class PMKID extends Module
{
    public function route()
    {
        switch ($this->request->action) {
            case 'refreshInfo':
                $this->refreshInfo();
                break;
            case 'refreshOutput':
                $this->refreshOutput();
                break;
            case 'refreshStatus':
                $this->refreshStatus();
                break;
            case 'handleDependencies':
                $this->handleDependencies();
                break;
            case 'handleDependenciesStatus':
                $this->handleDependenciesStatus();
                break;
            case 'getInterfaces':
                $this->getInterfaces();
                break;
            case 'startCapture':
                $this->startCapture();
                break;
						case 'stopCapture':
	              $this->stopCapture();
	              break;
            case 'getScanStatus':
              $this->getScanStatus();
              break;
            case 'getCaptures':
              $this->getCaptures();
              break;
            case 'loadCapture':
              $this->loadCapture();
              break;
            case 'convertCapture':
              $this->convertCapture();
              break;
        }
    }

    protected function checkDependency($dependencyName)
    {
        return ((exec("which {$dependencyName}") == '' ? false : true) && ($this->uciGet("pmkid.module.installed")));
    }

    protected function refreshInfo()
    {
        $moduleInfo     = @json_decode(file_get_contents("/pineapple/modules/PMKID/module.info"));
        $this->response = array(
            'title' => $moduleInfo->title,
            'version' => $moduleInfo->version
        );
    }

    private function handleDependencies()
    {
        if (!$this->checkDependency("hcxdumptool")) {
            $this->execBackground("/pineapple/modules/PMKID/scripts/dependencies.sh install " . $this->request->destination);
            $this->response = array(
                'success' => true
            );
        } else {
            $this->execBackground("/pineapple/modules/PMKID/scripts/dependencies.sh remove");
            $this->response = array(
                'success' => true
            );
        }
    }

    private function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/PMKID.progress')) {
            $this->response = array(
                'success' => true
            );
        } else {
            $this->response = array(
                'success' => false
            );
        }
    }

    private function refreshStatus()
    {
        if (!file_exists('/tmp/PMKID.progress')) {
            if (!$this->checkDependency("hcxdumptool")) {
                $installed    = false;
                $install      = "Not installed";
                $installLabel = "danger";
            } else {
                $installed    = true;
                $install      = "Installed";
                $installLabel = "success";
            }
        } else {
            $installed    = false;
            $install      = "Installing...";
            $installLabel = "warning";
        }

        $sdAvailable = $this->isSDAvailable();

        $this->response = array(
            "sdAvailable" => $sdAvailable,
            "installed" => $installed,
            "install" => $install,
            "installLabel" => $installLabel,
            "capturing" => file_exists('/tmp/pmkid.lock')
        );
    }

    private function getScanStatus()
    {
      $this->response = array(
        "capturing" => file_exists('/tmp/pmkid.lock')
      );
    }

    private function getCaptures()
    {
      $captureList = array_map(function($captureName) {
        return array_reverse(explode('/', $captureName))[0];
      }, glob("/pineapple/modules/PMKID/capture/*.log"));

      $this->response = array("captures" => $captureList);
    }

    private function loadCapture()
    {
      if (file_exists("/pineapple/modules/PMKID/capture/{$this->request->capture}")) {
        exec("cp /pineapple/modules/PMKID/capture/{$this->request->capture}  /tmp/pmkid.log");
      }
    }

    private function convertCapture()
    {
      if (file_exists("/pineapple/modules/PMKID/capture/{$this->request->capture}.16800")) {
        $this->response = array("download" => $this->downloadFile("/pineapple/modules/PMKID/capture/{$this->request->capture}.16800"));
      } else if (file_exists("/pineapple/modules/PMKID/capture/{$this->request->capture}")) {
        exec("hcxpcaptool -z /pineapple/modules/PMKID/capture/{$this->request->capture}.16800 /pineapple/modules/PMKID/capture/{$this->request->capture}");
        $this->response = array("download" => $this->downloadFile("/pineapple/modules/PMKID/capture/{$this->request->capture}.16800"));
      }
    }

    private function refreshOutput()
    {
        if (file_exists("/tmp/pmkid.log")) {
            $output = file("/tmp/pmkid.log");

            if (!empty($output)) {

                $filteredOutput = array();

                foreach ($output as $line_num => $line) {
                  if (strpos($line, '$HEX') === false) array_push($filteredOutput, $line);
                }

                $output = implode("\n", $filteredOutput);

                // $startNic
                preg_match_all('/MAC ACCESS POINT[\.]*?: ([a-f0-9]*?) \(start NIC\)/', $output, $startNic);
                // $accessPoints
                preg_match_all('/([a-f0-9]*) -> [a-f0-9]*? (.*?)\s\[PROBERESPONSE.*?]/m', $output, $accessPoints);
                // $pmkids
								preg_match_all('/([a-f0-9]*) -> [a-f0-9]* \[FOUND PMKID.*?]/m', $output, $pmkids);

                $startNic = count($startNic[0]) > 0 ? $startNic[1][0] : '';
								$pmkids = count($pmkids[0]) > 0 ? $pmkids[1] : array();
								$accessPoints = array_reduce(array_keys($accessPoints[0]), function($total, $currentIndex) use ($accessPoints, $pmkids, $startNic)
								{
                    if (substr($startNic, 0, -3) == substr($accessPoints[1][$currentIndex], 0, -3)) return $total;
										$total[$accessPoints[1][$currentIndex]] = ["bssid" => $accessPoints[2][$currentIndex], "powned" => in_array($accessPoints[1][$currentIndex], $pmkids)];
										return $total;
								});
                $this->response = json_encode(array(
                    "accessPoints" => $accessPoints,
                    "pmkids" => $pmkids
                ));
            } else {
                $this->response = json_encode(array(
                    "accessPoints" => array(),
                    "pmkids" => array()
                ));
            }
        } else {
            $this->response = " ";
        }
    }

    private function getInterfaces()
    {
        exec("iwconfig 2> /dev/null | grep \"wlan*\" | awk '{print $1}'", $interfaceArray);

        $this->response = array(
            "interfaces" => $interfaceArray
        );
    }

    private function startCapture()
    {
        exec("/pineapple/modules/PMKID/scripts/capture.sh start {$this->request->interface} {$this->request->commandLineArguments}");
    }

		private function stopCapture()
		{
				exec("/pineapple/modules/PMKID/scripts/capture.sh stop");
		}
}
