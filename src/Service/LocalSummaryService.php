<?php

namespace App\Service;

class LocalSummaryService
{
    public function generateSummary(string $title, string $author, string $description): string
    {
        // Extract key sentences from the description
        $sentences = preg_split('/(?<=[.!?])\s+/', $description, -1, PREG_SPLIT_NO_EMPTY);
        
        // If description is short, just return it
        if (count($sentences) <= 3) {
            return $description;
        }
        
        // Otherwise, take the first sentence and a couple more important ones
        $summary = $sentences[0];
        
        // Add a middle sentence
        if (count($sentences) > 3) {
            $summary .= ' ' . $sentences[intval(count($sentences) / 2)];
        }
        
        // Add the last sentence
        $summary .= ' ' . $sentences[count($sentences) - 1];
        
        // Add a generic intro
        $intro = "\"" . $title . "\" by " . $author . " is a compelling work that captivates readers. ";
        
        return $intro . $summary;
    }
}