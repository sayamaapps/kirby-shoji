<?php

namespace Sayamaapps\Shoji;

/**
 * Runs a call whose failure is read from its return value (false, null). PHP
 * still reports that failure as a warning; here the warning is dropped for the
 * duration of the call, once, instead of an error-control operator at each
 * call site. Callers that need the failure check the return value.
 *
 * @package   Kirby Shoji
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-shoji
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Quiet
{
	/**
	 * @template T
	 * @param callable(): T $call
	 * @return T
	 */
	public static function run(callable $call): mixed
	{
		set_error_handler(static fn (): bool => true);
		try {
			return $call();
		} finally {
			restore_error_handler();
		}
	}
}
