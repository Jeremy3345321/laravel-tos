<?php

namespace App\Services\Bloom;

/**
 * Labeled training examples for the Bloom's Taxonomy logistic regression
 * classifier. Add more rows here (and re-run `php artisan bloom:train`)
 * to improve accuracy — especially with real DepEd Cavite objectives.
 *
 * Levels are indexed 0-5 matching BloomClassifierService::LEVELS.
 */
class BloomTrainingData
{
    public const REMEMBERING = 0;
    public const UNDERSTANDING = 1;
    public const APPLYING = 2;
    public const ANALYZING = 3;
    public const EVALUATING = 4;
    public const CREATING = 5;

    /**
     * @return array<int, array{0: string, 1: int}>
     */
    public static function samples(): array
    {
        return [
            // Remembering
            ["Students will be able to define the parts of a plant cell.", self::REMEMBERING],
            ["Students will list the presidents of the Philippines in order.", self::REMEMBERING],
            ["Students will recall the formula for the area of a circle.", self::REMEMBERING],
            ["Students will identify the capital cities of ASEAN countries.", self::REMEMBERING],
            ["Students will name the layers of the earth.", self::REMEMBERING],
            ["Students will state the law of conservation of energy.", self::REMEMBERING],
            ["Students will label the parts of a microscope.", self::REMEMBERING],
            ["Students will match vocabulary words to their definitions.", self::REMEMBERING],
            ["Students will enumerate the branches of government.", self::REMEMBERING],
            ["Students will recognize the symbols used in a circuit diagram.", self::REMEMBERING],

            // Understanding
            ["Students will be able to explain the water cycle in their own words.", self::UNDERSTANDING],
            ["Students will summarize the main events of the story.", self::UNDERSTANDING],
            ["Students will describe how photosynthesis works.", self::UNDERSTANDING],
            ["Students will interpret the meaning of a given graph.", self::UNDERSTANDING],
            ["Students will classify animals based on their habitats.", self::UNDERSTANDING],
            ["Students will compare and contrast mitosis and meiosis conceptually.", self::UNDERSTANDING],
            ["Students will discuss the causes of World War I.", self::UNDERSTANDING],
            ["Students will translate the given sentence into Filipino.", self::UNDERSTANDING],
            ["Students will paraphrase a paragraph from the textbook.", self::UNDERSTANDING],
            ["Students will illustrate the stages of the rock cycle.", self::UNDERSTANDING],

            // Applying
            ["Students will solve quadratic equations using the quadratic formula.", self::APPLYING],
            ["Students will use the scientific method to conduct an experiment.", self::APPLYING],
            ["Students will demonstrate proper handwashing technique.", self::APPLYING],
            ["Students will apply grammar rules to correct sentences.", self::APPLYING],
            ["Students will calculate the area of irregular shapes.", self::APPLYING],
            ["Students will implement a simple sorting algorithm in code.", self::APPLYING],
            ["Students will construct a budget plan using given data.", self::APPLYING],
            ["Students will operate a Bunsen burner safely during the lab.", self::APPLYING],
            ["Students will practice long division using multi-digit numbers.", self::APPLYING],
            ["Students will execute the steps of a dance routine correctly.", self::APPLYING],

            // Analyzing
            ["Students will differentiate between mitosis and meiosis.", self::ANALYZING],
            ["Students will analyze the causes and effects of climate change.", self::ANALYZING],
            ["Students will compare the themes of two literary works.", self::ANALYZING],
            ["Students will examine the structure of a persuasive essay.", self::ANALYZING],
            ["Students will categorize rocks based on their formation process.", self::ANALYZING],
            ["Students will distinguish between fact and opinion in an article.", self::ANALYZING],
            ["Students will investigate the relationship between supply and demand.", self::ANALYZING],
            ["Students will deconstruct an argument to identify its assumptions.", self::ANALYZING],
            ["Students will break down a chemical reaction into its component steps.", self::ANALYZING],
            ["Students will contrast two economic systems based on given criteria.", self::ANALYZING],

            // Evaluating
            ["Students will evaluate the effectiveness of a given marketing strategy.", self::EVALUATING],
            ["Students will critique a peer's essay based on a rubric.", self::EVALUATING],
            ["Students will justify their position on a controversial issue.", self::EVALUATING],
            ["Students will assess the validity of a scientific claim.", self::EVALUATING],
            ["Students will judge the credibility of different historical sources.", self::EVALUATING],
            ["Students will defend a chosen solution to an environmental problem.", self::EVALUATING],
            ["Students will recommend improvements to an existing school policy.", self::EVALUATING],
            ["Students will appraise the quality of a research study's methodology.", self::EVALUATING],
            ["Students will rank the proposed solutions from most to least effective.", self::EVALUATING],
            ["Students will argue for or against a given ethical dilemma.", self::EVALUATING],

            // Creating
            ["Students will design an original experiment to test a hypothesis.", self::CREATING],
            ["Students will create a multimedia presentation about renewable energy.", self::CREATING],
            ["Students will compose an original short story using given vocabulary.", self::CREATING],
            ["Students will develop a business plan for a school enterprise.", self::CREATING],
            ["Students will construct a working model of a simple machine.", self::CREATING],
            ["Students will formulate a new theory to explain the observed data.", self::CREATING],
            ["Students will produce an original artwork inspired by a historical era.", self::CREATING],
            ["Students will devise a plan to reduce plastic waste in the community.", self::CREATING],
            ["Students will invent a new tool to solve an everyday problem.", self::CREATING],
            ["Students will draft a proposal for a new school program.", self::CREATING],
        ];
    }
}
