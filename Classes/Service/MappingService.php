<?php

namespace Lfda\Headless\Service;

use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MappingService
{

    public static function transform($row, array $config, $table)
    {
        $result = [];

        foreach ($config as $fieldId => $fieldConfig) {
            $value = MappingService::getProp($fieldId, $row);

            switch ($fieldConfig['type']) {
                case 'int':
                    $value = intval($value);
                    break;

                case 'string':
                    $value = strval($value);
                    break;

                case 'bool':
                    $value = boolval($value);
                    break;

                case 'datetime':
                    $o = new \DateTime();
                    $o->setTimestamp(intval($value));
                    $tz = new \DateTimeZone(date_default_timezone_get());
                    $o->setTimezone($tz);
                    $value = $o;
                    break;

                case 'images':
                    $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

                    $fileObjects = $fileRepository->findByRelation(
                        $table,
                        $fieldId,
                        MappingService::getProp('uid', $row)
                    );

                    $value = [];
                    foreach ($fileObjects as $file) {
                        if (array_key_exists('select', $fieldConfig)) {
                            $fields = GeneralUtility::trimExplode(',', $fieldConfig['select']);
                            $img = [];
                            foreach ($fields as $field) {
                                $img[$field] = $file->getProperty($field);
                            }
                            $value[] = $img;
                        } else {
                            $value[] = ['uid' => $file->getUId()];
                        }
                    }
                    break;

                case 'flexform':
                    $ffs = GeneralUtility::makeInstance(FlexFormService::class);
                    $value = $ffs->convertFlexFormContentToArray($value);
                    break;

                case 'children':
                    $children = [];
                    foreach ($value as $item) {
                        $children[] = MappingService::transform($item, $fieldConfig['mapping'], $fieldConfig['table']);
                    }
                    $value = $children;
                    break;

                case 'pass':
                default:
                    break;
            }

            // Map value to static values
            if (isset($fieldConfig['mapStatic']) && isset($fieldConfig['mapStatic'][$value])) {
                $value = $fieldConfig['mapStatic'][$value];
            }

            // If a name is set, return the value as the defined field
            if (isset($fieldConfig['as'])) {
                $result[$fieldConfig['as']] = $value;
            } else {
                $result[$fieldId] = $value;
            }
        }

        return $result;
    }

    public static function getProp($name, $row)
    {
        if (is_object($row)) {
            $methodName = 'get' . ucfirst($name);
            if (method_exists($row, $methodName)) {
                return $row->$methodName();
            }
        } else {
            return $row[$name];
        }

        return NULL;
    }

}
