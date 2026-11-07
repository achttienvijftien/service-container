<?php

namespace App;

class Collector {

	public array $foos;

	public function __construct( iterable $foos ) {
		$this->foos = iterator_to_array( $foos, false );
	}
}
