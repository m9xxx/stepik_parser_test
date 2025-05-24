<?php
namespace App\Services;

use App\Models\Parser;
use App\Utils\JsonDataReader;

class ParserService {
    private $availableParsers = ['stepik', 'skillbox', 'geekbrains'];

    public function runAllParsers() {
        $results = [];

        foreach ($this->availableParsers as $parserName) {
            $parser = new Parser($parserName);
            $results[$parserName] = $parser->run();
        }

        return $results;
    }

    public function runSpecificParser($parserName) {
        if (!in_array($parserName, $this->availableParsers)) {
            throw new \Exception("Неизвестный парсер: {$parserName}");
        }

        $parser = new Parser($parserName);
        return $parser->run();
    }

    public function getParserStatistics() {
        $jsonReader = new JsonDataReader(dirname(__DIR__, 2) . '/data');
        $statistics = [];

        foreach ($this->availableParsers as $parserName) {
            try {
                $courses = [];
                switch($parserName) {
                    case 'stepik':
                        $courses = $jsonReader->readStepikCourses();
                        break;
                    case 'skillbox':
                        $courses = $jsonReader->readSkillboxCourses();
                        break;
                    case 'geekbrains':
                        $courses = $jsonReader->readGeekBrainsCourses();
                        break;
                }

                $statistics[$parserName] = [
                    'totalCourses' => count($courses),
                    'lastUpdated' => $this->getLastModifiedTime($parserName)
                ];
            } catch (\Exception $e) {
                $statistics[$parserName] = [
                    'error' => $e->getMessage()
                ];
            }
        }

        return $statistics;
    }

    private function getLastModifiedTime($parserName) {
        $dataFile = dirname(__DIR__, 2) . "/data/{$parserName}_courses.json";
        return file_exists($dataFile) ? filemtime($dataFile) : null;
    }
}