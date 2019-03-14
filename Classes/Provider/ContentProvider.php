<?php

namespace Lfda\Headless\Provider;

use Lfda\Headless\Service\MappingService;
use Lfda\Headless\Service\SelectionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentProvider extends BaseProvider
{

    public function __construct()
    {
        $this->table = 'tt_content';
    }

    public function fetchData()
    {
        $selection = SelectionService::prepare($this->getConfiguration('selection/defaults', []));
        $selection['pid'] = SelectionService::make($this->getArgument('page', 0));

        $contents = $this->fetch($this->table, array_keys($this->getConfiguration('mapping')), $selection);
        $mapping = $this->getConfiguration('mapping', []);
        $render_configs = $this->getConfiguration('rendering', []);
        $group_by = $this->getConfiguration('group_by', false);
        $result = [];

        while ($row = $contents->fetch()) {
            $transformed = MappingService::transform($row, $mapping, $this->table);

            // rendering
            foreach ($render_configs as $target_key => $config) {
                if ($this->matches($config, $transformed)) {
                    try {
                        $renderer = GeneralUtility::makeInstance($config['renderer']);
                        $transformed[$target_key] = $renderer->execute($transformed, $config['options']);
                    } catch (Exception $exception) {
                        $transformed[$target_key] = 'Can\'t find renderer!!!';
                    }
                }
            }

            if ($group_by && array_key_exists($group_by, $transformed)) {
                $key = $transformed[$group_by];
                if (!isset($result[$key])) {
                    $result[$key] = [];
                }
                $result[$key][] = $transformed;
            } else {
                $result[] = $transformed;
            }

        }

        return $result;

    }

    protected function matches($config, $row)
    {
        foreach ($config['matching'] as $key => $value) {
            if (!array_key_exists($key, $row) || $row[$key] != $value) {
                return false;
            }
        }
        return true;
    }

}
