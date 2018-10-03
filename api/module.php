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
            case 'updateConfig':
                $this->updateConfig();
                break;
            case 'getConfig':
                $this->getConfig();
                break;
            case 'getAPs':
                $this->getAPs();
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
      $apFilename = str_replace(".log", ".aps", $this->request->capture);
      $this->response = $apFilename;
      if (file_exists("/pineapple/modules/PMKID/capture/${apFilename}")) {
        exec("cp /pineapple/modules/PMKID/capture/${apFilename} /tmp/pmkid-aps");
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

                // $pmkids
								preg_match_all('/([a-f0-9]*) -> [a-f0-9]* \[FOUND PMKID.*?]/m', $output, $pmkids);

								$pmkids = count($pmkids[0]) > 0 ? $pmkids[1] : array();

                $this->response = array(
                  "pmkids" => $pmkids
                );
            } else {
                $this->response = array(
                  "pmkids" => array()
                );
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

    private function updateConfig()
    {
      exec("uci set pmkid.module.commandLineArguments='{$this->request->commandLineArguments}'");
      exec("uci commit pmkid.module.commandLineArguments");
      exec("uci set pmkid.module.includeOrExclude='{$this->request->includeOrExclude}'");
      exec("uci commit pmkid.module.includeOrExclude");
    }

    private function getConfig()
    {
      $this->response = array(
        "commandLineArguments" => $this->uciGet("pmkid.module.commandLineArguments"),
        "includeOrExclude" => $this->uciGet("pmkid.module.includeOrExclude")
      );
    }

    private function getAPs()
    {
        if (!$this->request->skipScan) exec("/pineapple/modules/PMKID/scripts/scan.sh {$this->request->interface} {$this->request->duration}");
        $result = file_get_contents("/tmp/pmkid-aps");
        $apList = array_map(function ($line) {
          $lineExploded = explode(',', $line);
          return array("bssid"=> $lineExploded[0], "essid" => $lineExploded[1]);
        }, array_filter(explode("\n", $result)));
        $this->response = array(
          "aps" => $apList
        );
    }

    private function startCapture()
    {
        if (isset($this->request->selectedAps)) {
          $fp = fopen("/tmp/pmkid-selectedAps", "w");
          fwrite($fp, implode("\n", $this->request->selectedAps));
          fclose($fp);
        }
        exec("/pineapple/modules/PMKID/scripts/capture.sh start {$this->request->interface} {$this->request->includeOrExclude} {$this->request->commandLineArguments}");
    }

		private function stopCapture()
		{
				exec("/pineapple/modules/PMKID/scripts/capture.sh stop");
		}
}
