<?php

namespace App\Console\Commands;

use App\Services\Bloom\BloomClassifierService;
use App\Services\Bloom\BloomTrainingData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * php artisan bloom:train
 *
 * Trains the multinomial logistic regression model on the labeled
 * objectives in BloomTrainingData and writes the weights to
 * storage/app/ml/bloom_model.json for BloomClassifierService to load.
 *
 * Re-run this any time you add more labeled examples to
 * BloomTrainingData::samples() to improve accuracy.
 */
class TrainBloomModel extends Command
{
    protected $signature = 'bloom:train {--epochs=300} {--lr=0.5} {--lambda=0.001}';

    protected $description = "Train the Bloom's Taxonomy logistic regression classifier";

    public function handle(): int
    {
        $samples = BloomTrainingData::samples();
        $levels = BloomClassifierService::LEVELS;
        $numClasses = count($levels);

        $this->info('Building vocabulary from '.count($samples).' labeled objectives...');

        $vocab = [];
        foreach ($samples as [$text, $label]) {
            foreach (BloomClassifierService::tokenize($text) as $tok) {
                $vocab[$tok] = true;
            }
        }
        $vocab = array_keys($vocab);
        sort($vocab);
        $vocabIndex = array_flip($vocab);
        $vocabSize = count($vocab);

        $this->info("Vocabulary size: {$vocabSize} words");

        $X = [];
        $y = [];
        foreach ($samples as [$text, $label]) {
            $vec = array_fill(0, $vocabSize, 0.0);
            foreach (BloomClassifierService::tokenize($text) as $tok) {
                if (isset($vocabIndex[$tok])) {
                    $vec[$vocabIndex[$tok]] += 1.0;
                }
            }
            $X[] = $vec;
            $y[] = $label;
        }

        $numSamples = count($X);
        $weights = array_fill(0, $numClasses, array_fill(0, $vocabSize, 0.0));
        $bias = array_fill(0, $numClasses, 0.0);

        $lr = (float) $this->option('lr');
        $epochs = (int) $this->option('epochs');
        $lambda = (float) $this->option('lambda');

        $bar = $this->output->createProgressBar($epochs);
        $bar->start();

        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $gradW = array_fill(0, $numClasses, array_fill(0, $vocabSize, 0.0));
            $gradB = array_fill(0, $numClasses, 0.0);

            for ($i = 0; $i < $numSamples; $i++) {
                $z = [];
                for ($c = 0; $c < $numClasses; $c++) {
                    $dot = $bias[$c];
                    foreach ($X[$i] as $f => $val) {
                        if ($val != 0.0) {
                            $dot += $weights[$c][$f] * $val;
                        }
                    }
                    $z[$c] = $dot;
                }

                $max = max($z);
                $exps = array_map(fn ($v) => exp($v - $max), $z);
                $sum = array_sum($exps);
                $probs = array_map(fn ($v) => $v / $sum, $exps);

                $trueClass = $y[$i];
                for ($c = 0; $c < $numClasses; $c++) {
                    $error = $probs[$c] - ($c === $trueClass ? 1.0 : 0.0);
                    foreach ($X[$i] as $f => $val) {
                        if ($val != 0.0) {
                            $gradW[$c][$f] += $error * $val;
                        }
                    }
                    $gradB[$c] += $error;
                }
            }

            for ($c = 0; $c < $numClasses; $c++) {
                for ($f = 0; $f < $vocabSize; $f++) {
                    $reg = $lambda * $weights[$c][$f];
                    $weights[$c][$f] -= $lr * (($gradW[$c][$f] / $numSamples) + $reg);
                }
                $bias[$c] -= $lr * ($gradB[$c] / $numSamples);
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $model = [
            'vocab' => $vocab,
            'weights' => $weights,
            'bias' => $bias,
            'levels' => $levels,
            'trained_at' => now()->toIso8601String(),
            'sample_count' => $numSamples,
        ];

        Storage::put('ml/bloom_model.json', json_encode($model));

        $this->info('Model trained and saved to storage/app/ml/bloom_model.json');
        $this->table(['Metric', 'Value'], [
            ['Training samples', $numSamples],
            ['Vocabulary size', $vocabSize],
            ['Classes', $numClasses],
            ['Epochs', $epochs],
        ]);

        return self::SUCCESS;
    }
}
