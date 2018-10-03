registerController('PMKID_Controller', ['$api', '$scope', '$rootScope', '$interval', '$timeout', function($api, $scope, $rootScope, $interval, $timeout) {
  $scope.title = "Loading...";
  $scope.version = "Loading...";

  $scope.refreshInfo = (function() {
    $api.request({
      module: 'PMKID',
      action: "refreshInfo"
    }, function(response) {
      $scope.title = response.title;
      $scope.version = "v" + response.version;
    })
  });

  $scope.refreshInfo();

}]);

registerController('PMKID_ControlsController', ['$api', '$scope', '$rootScope', '$interval', '$timeout', function($api, $scope, $rootScope, $interval, $timeout) {
  $scope.install = "Loading...";
  $scope.installLabel = "default";
  $scope.processing = false;
  $scope.sdAvailable = false;

  $rootScope.status = {
    installed: false
  };

  $scope.refreshStatus = (function() {
    $api.request({
      module: "PMKID",
      action: "refreshStatus"
    }, function(response) {
      $rootScope.status.installed = response.installed;
      $scope.sdAvailable = response.sdAvailable;
      $scope.install = response.install;
      $scope.installLabel = response.installLabel;

    })
  });

  $scope.handleDependencies = (function(param) {
    if (!$rootScope.status.installed)
      $scope.install = "Installing...";
    else
      $scope.install = "Removing...";

    $api.request({
      module: 'PMKID',
      action: 'handleDependencies',
      destination: param
    }, function(response) {
      if (response.success === true) {
        $scope.installLabel = "warning";
        $scope.processing = true;

        $scope.handleDependenciesInterval = $interval(function() {
          $api.request({
            module: 'PMKID',
            action: 'handleDependenciesStatus'
          }, function(response) {
            if (response.success === true) {
              $scope.processing = false;
              $scope.refreshStatus();
              $interval.cancel($scope.handleDependenciesInterval);
            }
          });
        }, 5000);
      }
    });
  });

  $scope.refreshStatus();

}]);

registerController('PMKID_ScanController', ['$api', '$scope', '$rootScope', '$timeout', '$interval', '$filter', function($api, $scope, $rootScope, $timeout, $interval, $filter) {
  $scope.refreshLabelON = "default";
  $scope.refreshLabelOFF = "danger";

  $scope.interfaces = [];
  $scope.aps = [];
  $scope.pmkids = [];
  $scope.captures = [];
  $scope.commandLineArguments = '--enable_status 3';
  $scope.duration = 30;

  $scope.getInterfaces = (function() {
    $api.request({
      module: 'PMKID',
      action: 'getInterfaces'
    }, function(response) {
      $scope.interfaces = response.interfaces;
      $scope.selectedInterface = $scope.interfaces[0];
    });
  });

  $scope.captureLabel = "warning";
  $scope.captureText = "Start Capture";
  $scope.scanLabel = "success";
  $scope.scanText = "Scan for APs";
  $scope.captureStarting = false;
  $scope.captureStopping = false;
  $scope.scanning = false;
  $scope.capturing = false;

  $scope.toggleCapture = (function() {
    $scope.capturing ? $scope.stopCapture() : $scope.startCapture();
  });

  $scope.updateConfig = (function() {
    $api.request({
      module: 'PMKID',
      action: 'updateConfig',
      commandLineArguments: $scope.commandLineArguments,
      includeOrExclude: $scope.includeOrExclude
    })
  });

  $scope.getConfig = (function() {
    $api.request({
      module: 'PMKID',
      action: 'getConfig'
    }, function (response) {
      $scope.commandLineArguments = response.commandLineArguments
      $scope.includeOrExclude = response.includeOrExclude
    })
  })

  $scope.startCapture = (function() {
    $scope.captureLabel = "warning";
    $scope.captureText = "Starting...";
    $scope.captureStarting = true;
    var selectedAps = $("#apList input:checked").map((index, input) => { return $(input).val() }).toArray();

    $api.request({
      module: 'PMKID',
      action: 'startCapture',
      interface: $scope.selectedInterface,
      includeOrExclude: $scope.includeOrExclude,
      commandLineArguments: $scope.commandLineArguments,
      selectedAps
    }, function(response) {
      $scope.captureLabel = "danger";
      $scope.captureText = "Stop Capture";
      $scope.captureStarting = false;
      $scope.capturing = true;
      $scope.refreshOutput();
    });
  });

  $scope.stopCapture = (function() {
    $scope.captureLabel = "warning";
    $scope.captureText = "Stopping...";
    $scope.captureStopping = true;

    $api.request({
      module: 'PMKID',
      action: 'stopCapture'
    }, function(response) {
      $scope.captureLabel = "success";
      $scope.captureText = "Start Capture";
      $scope.captureStopping = false;
      $scope.capturing = false;
      $scope.getCaptures();
    });
  })

  $scope.refreshOutput = (function() {
    $api.request({
      module: "PMKID",
      action: "refreshOutput",
      filter: $scope.filter
    }, function(response) {
      $scope.output = response;
      $scope.pmkids = response.pmkids;
    })
  });

  $scope.clearOutput = (function() {
    $api.request({
      module: "PMKID",
      action: "clearOutput"
    }, function(response) {
      $scope.refreshOutput();
    })
  });

  $scope.getScanStatus = (function() {
    $api.request({
      module: 'PMKID',
      action: 'getScanStatus'
    }, function(response) {
      $scope.capturing = response.capturing;
      if ($scope.capturing) {
        $scope.captureLabel = "danger";
        $scope.captureText = "Stop Capture";
      }
    })
  });

  $scope.getCaptures = (function() {
    $api.request({
      module: 'PMKID',
      action: 'getCaptures'
    }, function(response) {
      $scope.captures = response.captures;
      if ($scope.captures.length && !$scope.selectedCapture) $scope.selectedCapture = $scope.captures[0];
    });
  });

  $scope.loadCapture = (function() {
    $api.request({
      module: 'PMKID',
      action: 'loadCapture',
      capture: $scope.selectedCapture
    }, function () {
      $scope.getAPs(true);
    })
  });

  $scope.convertCapture = (function() {
    $api.request({
      module: 'PMKID',
      action: 'convertCapture',
      capture: $scope.selectedCapture.split('.')[0]
    }, function(response) {
      if (response.error === undefined) {
        window.location = '/api/?download=' + response.download;
      }
    });
  });

  $scope.getAPs = (function(skipScan) {
    $scope.scanning = true;
    $scope.scanLabel = 'warning';
    $scope.scanText = 'Scanning';
    $api.request({
      module: 'PMKID',
      action: 'getAPs',
      interface: $scope.selectedInterface,
      duration: $scope.duration,
      skipScan
    }, function(response) {
      $scope.aps = response.aps;
      if ($scope.aps.length) $scope.captureLabel = 'success';
      $scope.scanning = false;
      $scope.scanLabel = 'success';
      $scope.scanText = 'Scan for APs';
    })
  });

  $scope.output = 'Loading...';
  $scope.pmkids = [];

  $scope.getConfig();
  $scope.getInterfaces();
  $scope.getScanStatus();
  $scope.refreshOutput();
  $scope.getAPs(true);
  $interval(function() {
    $scope.refreshOutput();
  }, 2000);
  $scope.getCaptures();
}]);
