#!/usr/bin/php
<?php
require('aws-sdk/aws-autoloader.php');

$shortOpts = 'p:r:i:CLDm:w:c:l';
$longOpts = array('profile:', 'region:', 'instanceId:', 'ec2Metric', 'elbMetric', 'rdsMetric', 'metric:', 'warning:', 'critical:', 'list');
$options = getopt($shortOpts, $longOpts);

$required = array('profile', 'region', 'instance', 'namespace', 'dimension', 'metric', 'warning', 'critical', 'list');
$opts = array_fill_keys($required, null);

foreach ($options as $key => $value) {
	switch ($key) {
		case 'p':
		case 'profile':
			$opts['profile'] = $value;
			break;
		case 'r':
		case 'region':
			$opts['region'] = $value;
			break;
		case 'i':
		case 'instanceId':
			$opts['instance'] = $value;
			break;
		case 'C':
		case 'ec2Metric':
			$opts['namespace'] = 'AWS/EC2';
			$opts['dimension'] = 'InstanceId';
			break;
		case 'L':
		case 'elbMetric':
			$opts['namespace'] = 'AWS/ELB';
			$opts['dimension'] = 'LoadBalancerName';
			break;
		case 'D':
		case 'rdsMetric':
			$opts['namespace'] = 'AWS/RDS';
			$opts['dimension'] = 'DBInstanceIdentifier';
			break;
		case 'm':
		case 'metric':
			$opts['metric'] = $value;
			break;
		case 'w':
		case 'warning':
			$opts['warning'] = $value;
			break;
		case 'c':
		case 'critical':
			$opts['critical'] = $value;
			break;
		case 'l':
		case 'list':
			$opts['list'] = true;
			break;
	}
}

function usage() {
	echo 'Options:' . PHP_EOL;
	echo ' -p, --profile <string>     Credential profile to use. default: default' . PHP_EOL;
	echo ' -r, --region <string>      Region. Pulled from the credential profile unless provided. example: us-east-1' . PHP_EOL;
	echo ' -i, --instanceId <string>  InstanceID, LoadBalancerName, or DBInstanceIdentifier to check.' . PHP_EOL;
	echo ' -C, --ec2Metric            Use when checking EC2.' . PHP_EOL;
	echo ' -L, --elbMetric            Use when checking ELB.' . PHP_EOL;
	echo ' -D, --rdsMetric            Use when checking RDS.' . PHP_EOL;
	echo ' -m, --metric <string>      The CloudWatch metric to monitor.' . PHP_EOL;
	echo ' -w, --warning <integer>    The warning threshold for this metric.' . PHP_EOL;
	echo ' -c, --critical <integer>   The critical threshold for this metric.' . PHP_EOL;
	echo ' -l, --list                 Use to list available instances or metrics to monitor.' . PHP_EOL;
	exit(3);
}

function convertUnit($unit) {
	$units = array(
		'Seconds' => 's', 'Microseconds' => 'μs', 'Milliseconds' => 'ms',
		'Bytes' => 'Bytes', 'Kilobytes' => 'kB', 'Megabytes' => 'MB', 'Gigabytes' => 'GB', 'Terabytes' => 'TB',
		'Bits' => 'Bits', 'Kilobits' => 'kb', 'Megabits' => 'Mb', 'Gigabits' => 'Gb', 'Terabits' => 'Tb',
		'Percent' => '%', 'Count' => '',
		'Bytes/Second' => 'Bytes/s', 'Kilobytes/Second' => 'kB/s', 'Megabytes/Second' => 'MB/s', 'Gigabytes/Second' => 'GB/s', 'Terabytes/Second' => 'TB/s',
		'Bits/Second' => 'Bits/s', 'Kilobits/Second' => 'kb/s','Megabits/Second' => 'Mb/s', 'Gigabits/Second' => 'Gb/s', 'Terabits/Second' => 'Tb/s',
		'Count/Second' => '/s',
		'None' => ''
	);

	if (array_key_exists($unit, $units)) {
		return $units[$unit];
	} else {
		return $unit;
	}
}

if ($opts['profile'] && $opts['region']) {
	$clientOpts = array('profile' => $opts['profile'], 'region' => $opts['region'], 'version' => 'latest');
} elseif ($opts['profile']) {
	$clientOpts = array('profile' => $opts['profile'], 'version' => 'latest');
} elseif ($opts['region']) {
	$clientOpts = array('profile' => 'default', 'region' => $opts['region'], 'version' => 'latest');
} else {
	echo 'Must specify profile (-p), region (-r), or both!' . PHP_EOL;
	usage();
}

if (!$opts['instance']) {
	if ($opts['list'] && $opts['namespace'] && $opts['dimension']) {
		switch ($opts['namespace']) {
			case 'AWS/EC2':
				$listClient = new Aws\Ec2\Ec2Client($clientOpts);
				$result = $listClient->describeInstances();
				printf('%-20s %-60s %-14s %-16s' . PHP_EOL, 'InstanceId', 'InstanceName', 'InstanceState', 'PrivateIpAddress');
				foreach ($result['Reservations'] as $reservation) {
					foreach ($reservation['Instances'] as $instance) {
						foreach ($instance['Tags'] as $tag) {
							if ($tag['Key'] == 'Name') {
								$instanceName = $tag['Value'];
							}
						}
						$instanceId = !empty($instance['InstanceId']) ? $instance['InstanceId'] : null;
						$instanceState = !empty($instance['State']['Name']) ? $instance['State']['Name'] : null;
						$privateIpAddress = !empty($instance['PrivateIpAddress']) ? $instance['PrivateIpAddress'] : null;
						printf('%-20s %-60s %-14s %-16s' . PHP_EOL, $instanceId, $instanceName, $instanceState, $privateIpAddress);
					}
				}
				break;
			case 'AWS/ELB':
				$listClient = new Aws\ElasticLoadBalancing\ElasticLoadBalancingClient($clientOpts);
				$result = $listClient->describeLoadBalancers();
				printf('%-40s %-16s %-80s' . PHP_EOL, 'LoadBalancerName', 'Scheme', 'DNSName');
				foreach ($result['LoadBalancerDescriptions'] as $loadbalancer) {
					$loadBalancerName = !empty($loadbalancer['LoadBalancerName']) ? $loadbalancer['LoadBalancerName'] : null;
					$scheme = !empty($loadbalancer['Scheme']) ? $loadbalancer['Scheme'] : null;
					$dnsName = !empty($loadbalancer['DNSName']) ? $loadbalancer['DNSName'] : null;
					printf('%-40s %-16s %-80s' . PHP_EOL, $loadBalancerName, $scheme, $dnsName);
				}
				break;
			case 'AWS/RDS':
				$listClient = new Aws\Rds\RdsClient($clientOpts);
				$result = $listClient->describeDBInstances();
				printf('%-60s %-60s %-16s' . PHP_EOL, 'DBInstanceIdentifier', 'DBClusterIdentifier', 'DBInstanceState');
				foreach ($result['DBInstances'] as $dbinstance) {
					$dBInstanceIdentifier = !empty($dbinstance['DBInstanceIdentifier']) ? $dbinstance['DBInstanceIdentifier'] : null;
					$dBClusterIdentifier = !empty($dbinstance['DBClusterIdentifier']) ? $dbinstance['DBClusterIdentifier'] : null;
					$dBInstanceStatus = !empty($dbinstance['DBInstanceStatus']) ? $dbinstance['DBInstanceStatus'] : null;
					printf('%-60s %-60s %-16s' . PHP_EOL, $dBInstanceIdentifier, $dBClusterIdentifier, $dBInstanceStatus);
				}
				break;
		}
		exit(3);
	} else {
		echo 'Must specify an instanceId (-i)!' . PHP_EOL;
		echo 'Replace with (-l) and add (-C, -L, -D) to show which ones are available.' . PHP_EOL;
		usage();
	}
} elseif (!$opts['namespace'] || !$opts['dimension']) {
	echo 'Must specify an instance type (-C, -L, -D)!' . PHP_EOL;
	usage();
} elseif (!$opts['metric']) {
	if ($opts['list']) {
		$listClient = new Aws\CloudWatch\CloudWatchClient($clientOpts);
		$dimensions = array('Name' => $opts['dimension'], 'Value' => $opts['instance']);
		$result = $listClient->listMetrics([
			'Dimensions' => [$dimensions],
			'Namespace' => $opts['namespace']
		]);
		foreach ($result['Metrics'] as $metric) {
			echo $metric['MetricName'] . PHP_EOL;
		}
		exit(3);
	} else {
		echo 'Must specify a metric (-m)!' . PHP_EOL;
		echo 'Replace with (-l) to show which ones are available.' . PHP_EOL;
		usage();
	}
} elseif (!$opts['warning'] || !$opts['critical']) {
	echo 'Must specify both warning (-w) and critical (-c) values!' . PHP_EOL;
	usage();
}

$client = new Aws\CloudWatch\CloudWatchClient($clientOpts);
$dimensions = array('Name' => $opts['dimension'], 'Value' => $opts['instance']);
$statistics = array('SampleCount', 'Average', 'Sum', 'Minimum', 'Maximum');
$result = $client->getMetricStatistics([
	'Dimensions' => [$dimensions],
	'EndTime' => time(),
	'MetricName' => $opts['metric'],
	'Namespace' => $opts['namespace'],
	'Period' => 300,
	'Statistics' => $statistics,
	'StartTime' => time() - 300
]);
$datapoint = $result['Datapoints'][0];
$average = $datapoint['Average'];
$unit = convertUnit($datapoint['Unit']);

if ($average >= $opts['critical']) {
	echo "CRITICAL: Average {$opts['metric']} is {$average}{$unit}" . PHP_EOL;
	exit(2);
} elseif ($average >= $opts['warning']) {
	echo "WARNING: Average {$opts['metric']} is {$average}{$unit}" . PHP_EOL;
	exit(1);
} else {
	echo "OK: Average {$opts['metric']} is {$average}{$unit}" . PHP_EOL;
	exit(0);
}
?>
