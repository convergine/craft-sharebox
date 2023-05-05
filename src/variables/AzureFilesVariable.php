<?php
/**
 * Craft ShareBox plugin for Craft CMS 4.x
 *
 */

namespace convergine\sharebox\variables;

use convergine\sharebox\ShareBox;

class AzureFilesVariable
{
	// Public Methods
	// =========================================================================

	public function files(array $criteria = [])
	{
		$content = ShareBox::getInstance()->frontendService->getHTML();

		return $content;
	}
}
