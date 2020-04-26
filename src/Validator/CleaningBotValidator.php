<?php

namespace App\Validator;

class CleaningBotValidator
{
    const MAP_ALLOWED_STRINGS = ['S', 'C', 'null'];
    const ALLOWED_COMMANDS = ['TR', 'TL', 'A', 'B', 'C'];

    /**
     * Validate content
     * @param $content
     * @return bool
     */
    public function isValid($content)
    {
        // content structure
        if (!array_key_exists('map', $content) ||
            !array_key_exists('start', $content) ||
            !array_key_exists('commands', $content) ||
            !array_key_exists('battery', $content)
        ) return false;

        // content elements types
        if (!is_array($content['map']) ||
            !is_array($content['start']) ||
            !is_array($content['commands']) ||
            !is_int($content['battery'])
        ) return false;

        // map allowed strings
        foreach ($content['map'] as $row) {
            foreach ($row as $value) {
                if (!in_array($value, self::MAP_ALLOWED_STRINGS)) return false;
            }
        }

        // commands allowed
        foreach ($content['commands'] as $command) {
            if (!in_array($command, self::ALLOWED_COMMANDS)) return false;
        }

        // start position structure
        if (!array_key_exists('X', $content['start']) ||
            !is_int($content['start']['X']) ||
            !array_key_exists('Y', $content['start']) |
            !is_int($content['start']['Y']) ||
            !array_key_exists('facing', $content['start']) ||
            !is_string($content['start']['facing'])
        ) return false;

        return true;
    }
}
