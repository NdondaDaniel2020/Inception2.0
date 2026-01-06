<?php

namespace Microthemer;

/*
 * TimerTrait
 *
 * measure the performance of functions
 */


namespace Microthemer;

trait TimerTrait {

	public $enabled = true;
	public $profiler = [];
	public $memoryProfiler = [];

	function getCallerFunction() {
		return debug_backtrace()[2]['function'];
	}

	function startT($fName = null, $memRealUse = false) {

		if (!$this->enabled || !$this->isEditing) {
			return;
		}

		$fName = $fName ?: $this->getCallerFunction();

		// Init time profiler
		if (!isset($this->profiler[$fName])) {
			$this->profiler[$fName] = [
				'sT'         => [],
				'eT'         => [],
				'calls'      => 0,
				'avg_time'   => 0,
				'total_time' => 0
			];
		}

		// Init memory profiler
		if (!isset($this->memoryProfiler[$fName])) {
			$this->memoryProfiler[$fName] = [
				'sMem'            => [],
				'eMem'            => [],
				'sPeak'           => [],
				'delta'           => [],
				'peak_window'     => [],
				'total_delta'     => 0,
				'avg_delta'       => 0,
				'avg_peak_window' => 0,
				'max_peak_window' => 0
			];
		}

		$callIndex = ++$this->profiler[$fName]['calls'];

		// Time start
		$this->profiler[$fName]['sT'][$callIndex] = microtime(true);

		// Memory start
		$this->memoryProfiler[$fName]['sMem'][$callIndex]  = memory_get_usage($memRealUse); // actual usage
		$this->memoryProfiler[$fName]['sPeak'][$callIndex] = memory_get_peak_usage(true); // allocator peak
	}

	function endT($fName = null, $memRealUse = false) {
		if (!$this->enabled || !$this->isEditing) {
			return;
		}

		$fName     = $fName ?: $this->getCallerFunction();
		$callIndex = $this->profiler[$fName]['calls'];

		// --- Time ---
		$eT  = microtime(true);
		$elT = $eT - $this->profiler[$fName]['sT'][$callIndex];

		$this->profiler[$fName]['eT'][$callIndex] = $eT;
		$this->profiler[$fName]['total_time']     += $elT;
		$this->profiler[$fName]['avg_time']       = round($this->profiler[$fName]['total_time'] / $callIndex, 6);

		// --- Memory ---
		$endMem  = memory_get_usage($memRealUse);       // actual retained usage
		$endPeak = memory_get_peak_usage(true);   // peak allocator usage

		$startMem  = $this->memoryProfiler[$fName]['sMem'][$callIndex];
		$startPeak = $this->memoryProfiler[$fName]['sPeak'][$callIndex];

		$deltaMem     = $endMem - $startMem;           // net retained usage
		$peakDeltaWin = max(0, $endPeak - $startPeak); // peak overhead during call

		$this->memoryProfiler[$fName]['eMem'][$callIndex]        = $endMem;
		$this->memoryProfiler[$fName]['delta'][$callIndex]       = $deltaMem;
		$this->memoryProfiler[$fName]['peak_window'][$callIndex] = $peakDeltaWin;

		$this->memoryProfiler[$fName]['total_delta'] += $deltaMem;
		$this->memoryProfiler[$fName]['avg_delta']   =
			$this->memoryProfiler[$fName]['total_delta'] / $callIndex;

		// Peak averages
		$this->memoryProfiler[$fName]['avg_peak_window'] =
			array_sum($this->memoryProfiler[$fName]['peak_window']) / count($this->memoryProfiler[$fName]['peak_window']);
		$this->memoryProfiler[$fName]['max_peak_window'] =
			max($this->memoryProfiler[$fName]['max_peak_window'], $peakDeltaWin);
	}

	function formatBytes($bytes, $precision = 2) {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$bytes = max($bytes, 0);
		$pow   = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
		$pow   = min($pow, count($units) - 1);
		$bytes /= (1 << (10 * $pow));

		return round($bytes, $precision) . ' <span class="amender-unit">'.$units[$pow].'</span>';
	}

	function showT() {
		$output = ['time' => $this->profiler, 'memory' => $this->memoryProfiler];

		foreach ($output['memory'] as &$data) {
			if (!empty($data['delta'])) {
				$data['delta_formatted'] = array_map([$this, 'formatBytes'], $data['delta']);
			}
			if (!empty($data['peak_window'])) {
				$data['peak_window_formatted'] = array_map([$this, 'formatBytes'], $data['peak_window']);
			}
			$data['avg_delta_formatted']       = $this->formatBytes($data['avg_delta']);
			$data['avg_peak_window_formatted'] = $this->formatBytes($data['avg_peak_window']);
			$data['max_peak_window_formatted'] = $this->formatBytes($data['max_peak_window']);
		}

		return '<pre>' . print_r($output, true) . '</pre>';
	}
}



