<?php

class Base
{
	public function visitorIsUsingUnsupportedBrowser()
	{
		$unsupported = [
			'SamsungBrowser',
			'UCBrowser',
			'MSIE',
			'Trident'
		];
		foreach ($unsupported as $string) {
			if (strpos($_SERVER['HTTP_USER_AGENT'], $string) !== false) {
				return true;
			}
		}

		return false;
	}

	public function pretty_dump($data)
    {
        echo '<pre>';var_dump($data);echo '</pre>';
    }
}