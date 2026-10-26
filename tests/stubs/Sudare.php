<?php
/**
 * Stand-in for the preview protection package Sudare, which Shoji does not
 * depend on: only its documented contract for other packages,
 * `\Sudare\Sudare::active()` (true when the request is behind the protection).
 * The test switches `$active` per check.
 */

namespace Sudare;

final class Sudare
{
	public static bool $active = false;

	public static function active(): bool
	{
		return self::$active;
	}
}
