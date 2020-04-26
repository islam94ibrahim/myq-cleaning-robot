<?php

namespace App\Command;

use App\Validator\CleaningBotValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleaningRobotCommand extends Command
{
    const BATTERY_CONSUMPTION = [
        'TL' => 1,
        'TR' => 1,
        'A' => 2,
        'B' => 3,
        'C' => 5
    ];

    const BACK_OFF_STRATEGIES = [
        ['TR', 'A', 'TL'],
        ['TR', 'A', 'TR'],
        ['TR', 'A', 'TR'],
        ['TR', 'B', 'TR', 'A'],
        ['TL', 'TL', 'A']
    ];

    /**
     * Cleaning bot validator
     */
    private $validator;

    /**
     * Robot operating space
     */
    private $map;

    /**
     * Robot Battery
     */
    private $battery;

    /**
     * Robot current X position
     */
    private $positionX;

    /**
     * Robot current Y Position
     */
    private $positionY;

    /**
     * Robot current direction
     */
    private $direction;

    /**
     * Operating space (map) visited points
     */
    private $visited = [];

    public function __construct(CleaningBotValidator $validator, String $name = null)
    {
        parent::__construct($name);

        $this->validator = $validator;
    }

    /**
     * Configure command name and required inputs
     */
    protected function configure()
    {
        $this
            ->setName('cleaning_robot')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'The path to the input source file.'
            )
            ->addArgument(
                'result',
                InputArgument::REQUIRED,
                'The path to the generated result file.'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sourcePath = $input->getArgument('source');
        $resultPath = $input->getArgument('result');

        if (!file_exists($sourcePath)) {
            $output->writeln('Source path is not valid!');
            return 0;
        }

        if (!file_exists(pathinfo($resultPath)['dirname'])) {
            $output->writeln('Result path is not valid!');
            return 0;
        }

        $content = json_decode(file_get_contents($sourcePath), true);

        // validate source file structure
        if (!$this->validator->isValid($content)) {
            $output->writeln('Source file content is not valid!');
            return 0;
        };

        $this->map = $content['map'];
        $this->battery = $content['battery'];
        $this->positionX = $content['start']['X'];
        $this->positionY = $content['start']['Y'];
        $this->direction = $content['start']['facing'];
        $this->visited[] = [$this->positionX, $this->positionY];
        $result = [
            'visited' => [(object)['X' => $this->positionX, 'Y' => $this->positionY]],
            'cleaned' => [],
            'final' => new \stdClass,
            'battery' => 0
        ];

        $output->writeln(sprintf(
            '- Cleaning robot started from point (%d,%d) facing %s.',
            $this->positionX,
            $this->positionY, $this->direction
        ));

        foreach ($content['commands'] as $command) {
            // if no more battery left, ignore remaining commands
            if ($this->battery - self::BATTERY_CONSUMPTION[$command] < 0) {
                $output->writeln('- Battery fully consumed, aborting.');
                break;
            }

            // deduct battery
            $this->battery -= self::BATTERY_CONSUMPTION[$command];

            if (in_array($command, ['TR', 'TL'])) {
                // direction change
                $this->direction = $this->changeDirection($command);
                $output->writeln(sprintf(
                    '- Performing command %s (new direction is %s).',
                    $command,
                    $this->direction
                ));
            } elseif (in_array($command, ['A', 'B'])) {
                // position change
                [$newPositionX, $newPositionY] = $this->move($command);
                $output->writeln(sprintf(
                    '- Performing command %s (Moving to point (%d,%d)).',
                    $command,
                    $newPositionX,
                    $newPositionY
                ));

                if (in_array($this->map[$newPositionY][$newPositionX], ['C', 'null'])) {
                    // if current position is an obstacle, initiate back off strategy
                    [$abort, $result] = $this->backOff($result, $output);

                    if ($abort) break;
                } else {
                    // assign new positions
                    $this->positionX = $newPositionX;
                    $this->positionY = $newPositionY;

                    // check if current point is not already visited before
                    if (!$this->isVisited()) {
                        $result['visited'][] = (object)[
                            'X' => $this->positionX,
                            'Y' => $this->positionY
                        ];
                        $this->visited[] = [$this->positionX, $this->positionY];
                    }
                }
            } else {
                $output->writeln(sprintf(
                    '- Performing command %s (Cleaning point (%d,%d)).',
                    $command,
                    $this->positionX,
                    $this->positionY
                ));

                if (in_array($this->map[$this->positionY][$this->positionX], ['C', 'null', ''])) {
                    // if current position is an obstacle or already cleaned, initiate back off strategy
                    [$abort, $result] = $this->backOff($result, $output);

                    if ($abort) break;
                } else {
                    $this->map[$this->positionY][$this->positionX] = '';
                    $result['cleaned'][] = (object)[
                        'X' => $this->positionX,
                        'Y' => $this->positionY
                    ];
                }
            }
        }

        $result['final'] = (object)[
            'X' => $this->positionX,
            'Y' => $this->positionY,
            'facing' => $this->direction
        ];

        $result['battery'] = $this->battery;

        file_put_contents($resultPath, json_encode($result));
        $output->writeln('- Cleaning bot finished, Result file created successfully!');

        return 0;
    }


    /**
     * Change the direction of the robot (Turn left or Turn right)
     *
     * @param $command
     * @return string
     */
    private function changeDirection($command)
    {
        if ($this->direction === 'N') return $command === 'TR' ? 'E' : 'W';

        if ($this->direction === 'E') return $command === 'TR' ? 'S' : 'N';

        if ($this->direction === 'S') return $command === 'TR' ? 'W' : 'E';

        return $command === 'TR' ? 'N' : 'S';
    }


    /**
     * Move the robot (Advance or Back)
     *
     * @param $command
     * @return array
     */
    private function move($command)
    {
        $newPositionX = $this->positionX;
        $newPositionY = $this->positionY;

        if ($this->direction === 'N') {
            $newPositionY = $command === 'A' ? --$newPositionY : ++$newPositionY;
        } elseif ($this->direction === 'E') {
            $newPositionX = $command === 'A' ? ++$newPositionX : --$newPositionX;
        } elseif ($this->direction == 'S') {
            $newPositionY = $command === 'A' ? ++$newPositionY : --$newPositionY;
        } else {
            $newPositionX = $command === 'A' ? --$newPositionX : ++$newPositionX;
        }

        $maxMapHeight = count($this->map) - 1;
        $maxMapRowWidth = count($this->map[$this->positionY]) - 1;

        // don't go over the max map height
        $newPositionY = $newPositionY > $maxMapHeight ? $maxMapHeight : $newPositionY;
        // don't go over the max map row width
        $newPositionX = $newPositionX > $maxMapRowWidth ? $maxMapRowWidth : $newPositionX;

        // if new position (X or Y) is negative, return zero instead
        return [
            $newPositionX < 0 ? 0 : $newPositionX,
            $newPositionY < 0 ? 0 : $newPositionY
        ];
    }

    /**
     * Check if the current point (X,Y) is already visited
     *
     * @return bool
     */
    private function isVisited()
    {
        foreach ($this->visited as $position) {
            if ($position[0] === $this->positionX && $position[1] === $this->positionY) return true;
        }

        return false;
    }

    /**
     * Initiate back off strategies until the robot is not stuck
     *
     * @param $result
     * @param $output
     * @param int $index
     * @param mixed $strategy
     * @return array
     */
    private function backOff($result, $output, $index = 0, $strategy = self::BACK_OFF_STRATEGIES[0])
    {
        $output->writeln(sprintf('- Back off %s strategy initiated.', json_encode($strategy)));

        foreach ($strategy as $command) {
            // if no more battery left, abort and return result
            if ($this->battery - self::BATTERY_CONSUMPTION[$command] < 0) {
                $output->writeln('- Battery fully consumed, aborting.');
                return [
                    true, // boolean used to know if the robot should abort during back off strategy
                    $result
                ];
            }

            // deduct battery
            $this->battery -= self::BATTERY_CONSUMPTION[$command];

            if (in_array($command, ['TR', 'TL'])) {
                // direction change
                $this->direction = $this->changeDirection($command);
                $output->writeln(sprintf(
                    '- Performing command %s (new direction is %s).',
                    $command,
                    $this->direction
                ));
            } elseif (in_array($command, ['A', 'B'])) {
                // position change
                [$newPositionX, $newPositionY] = $this->move($command);
                $output->writeln(sprintf(
                    '- Performing command %s (Moving to point (%d,%d)).',
                    $command,
                    $newPositionX,
                    $newPositionY
                ));

                // if current position is an obstacle, ignore rest of the sequence
                if (in_array($this->map[$newPositionY][$newPositionX], ['C', 'null'])) {
                    $index++;
                    // if we don't go through all back off strategies, recursively try next one, otherwise abort
                    return $index > count(self::BACK_OFF_STRATEGIES) - 1 ?
                        [false, $result] :
                        $this->backOff($result, $output, $index, self::BACK_OFF_STRATEGIES[$index]);
                }

                // assign new positions
                $this->positionX = $newPositionX;
                $this->positionY = $newPositionY;

                // check if current point is not already visited before
                if (!$this->isVisited()) {
                    $result['visited'][] = (object)[
                        'X' => $this->positionX,
                        'Y' => $this->positionY
                    ];
                    $this->visited[] = [$this->positionX, $this->positionY];
                }
            }
        }

        return [
            false, // back off strategy performed successfully, robot continues next commands
            $result
        ];
    }
}
