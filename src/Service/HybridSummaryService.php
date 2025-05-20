<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class HybridSummaryService
{
    private $httpClient;
    private $params;
    private $cache;
    private $retryAttempts = 1; // Reduced to minimize waiting time
    private $retryDelay = 1; // Reduced to minimize waiting time

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->cache = new FilesystemAdapter('book_summaries', 0, $params->get('kernel.cache_dir'));
    }

    public function generateSummary(string $title, string $author, string $description): string
    {
        // Create a cache key based on the book details
        $cacheKey = 'summary_' . md5($title . $author . $description);
        
        // Try to get from cache first
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($title, $author, $description) {
            // Cache for 30 days
            $item->expiresAfter(30 * 24 * 3600);
            
            // Try OpenAI first
            $openAiSummary = $this->tryOpenAiSummary($title, $author, $description);
            if (!$this->isErrorMessage($openAiSummary)) {
                return $openAiSummary;
            }
            
            // If OpenAI fails, use the local fallback
            return $this->generateLocalSummary($title, $author, $description);
        });
    }
    
    /**
     * Try to generate a summary using OpenAI
     */
    private function tryOpenAiSummary(string $title, string $author, string $description): string
    {
        // Check if API key is set
        $apiKey = $this->params->get('openai_api_key');
        if (empty($apiKey)) {
            return 'OpenAI API key is not configured. Using local summary instead.';
        }

        $attempts = 0;
        
        while ($attempts <= $this->retryAttempts) {
            try {
                if ($attempts > 0) {
                    // Wait before retrying with exponential backoff
                    $sleepTime = $this->retryDelay * pow(2, $attempts - 1);
                    sleep($sleepTime);
                }
                
                $prompt = "Generate a concise summary for the book '{$title}' by {$author}. Book description: {$description}";

                $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-3.5-turbo',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a helpful assistant that generates concise book summaries.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'max_tokens' => 500,
                        'temperature' => 0.7,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                
                if ($statusCode === 200) {
                    $data = $response->toArray();
                    if (!isset($data['choices'][0]['message']['content'])) {
                        throw new \Exception('Unexpected response format from OpenAI API');
                    }
                    
                    return $data['choices'][0]['message']['content'];
                }
                
                // Handle different status codes
                if ($statusCode === 429) {
                    throw new \Exception('Rate limit exceeded. Please try again later or check your OpenAI API quota.');
                } else {
                    // Handle other error status codes
                    throw new \Exception('OpenAI API returned status code: ' . $statusCode);
                }
                
            } catch (\Exception $e) {
                // Log the error
                error_log('OpenAI API Error: ' . $e->getMessage());
                
                if ($attempts < $this->retryAttempts) {
                    $attempts++;
                    continue;
                }
                
                // Return error message
                return 'Unable to generate AI summary: ' . $e->getMessage();
            }
        }
        
        return 'Unable to generate AI summary after multiple attempts. Using local summary instead.';
    }
    
    /**
     * Generate a summary locally based on the book description
     */
    private function generateLocalSummary(string $title, string $author, string $description): string
    {
        // Extract significant sentences from the description
        $sentences = $this->splitIntoSentences($description);
        
        // If description is very short, just return it
        if (count($sentences) <= 3) {
            return $description;
        }
        
        // Simple extractive summary algorithm
        $significantSentences = $this->extractSignificantSentences($sentences);
        
        // Format the summary
        $summary = "Summary of \"{$title}\" by {$author}:\n\n";
        $summary .= implode(' ', $significantSentences);
        
        return $summary;
    }
    
    /**
     * Split text into sentences
     */
    private function splitIntoSentences(string $text): array
    {
        // Simple sentence splitting (can be improved)
        $text = str_replace(["\n", "\r"], ' ', $text);
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_map('trim', $sentences);
    }
    
    /**
     * Extract the most significant sentences for a summary
     */
    private function extractSignificantSentences(array $sentences): array
    {
        // If there are few sentences, use all of them
        if (count($sentences) <= 5) {
            return $sentences;
        }
        
        // Calculate word frequency
        $wordFrequency = [];
        foreach ($sentences as $sentence) {
            $words = $this->extractWords($sentence);
            foreach ($words as $word) {
                if (!isset($wordFrequency[$word])) {
                    $wordFrequency[$word] = 0;
                }
                $wordFrequency[$word]++;
            }
        }
        
        // Score sentences based on word frequency
        $sentenceScores = [];
        foreach ($sentences as $i => $sentence) {
            $words = $this->extractWords($sentence);
            $score = 0;
            foreach ($words as $word) {
                $score += $wordFrequency[$word];
            }
            // Normalize by sentence length to avoid bias towards longer sentences
            $score = $score / max(1, count($words));
            $sentenceScores[$i] = $score;
        }
        
        // Give higher score to first few sentences, which often contain key information
        if (isset($sentenceScores[0])) {
            $sentenceScores[0] *= 1.5;
        }
        if (isset($sentenceScores[1])) {
            $sentenceScores[1] *= 1.2;
        }
        
        // Get the top scoring sentences
        $numSentences = min(5, max(3, intval(count($sentences) / 3)));
        arsort($sentenceScores);
        $topSentenceIndices = array_keys(array_slice($sentenceScores, 0, $numSentences, true));
        
        // Sort the indices to maintain original order
        sort($topSentenceIndices);
        
        // Get the sentences in original order
        $significantSentences = [];
        foreach ($topSentenceIndices as $i) {
            $significantSentences[] = $sentences[$i];
        }
        
        return $significantSentences;
    }
    
    /**
     * Extract words from a sentence
     */
    private function extractWords(string $sentence): array
    {
        // Convert to lowercase and remove punctuation
        $sentence = strtolower($sentence);
        $sentence = preg_replace('/[^\p{L}\p{N}\s]/u', '', $sentence);
        
        // Split into words
        $words = preg_split('/\s+/', $sentence, -1, PREG_SPLIT_NO_EMPTY);
        
        // Remove common stop words
        $stopWords = ['a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'about', 'as', 'of', 'is', 'are', 'was', 'were'];
        $words = array_diff($words, $stopWords);
        
        return $words;
    }
    
    /**
     * Check if a message is an error message
     */
    private function isErrorMessage(string $message): bool
    {
        $errorPrefixes = [
            'Unable to generate AI summary',
            'OpenAI API',
            'Rate limit exceeded',
            'Error:',
        ];
        
        foreach ($errorPrefixes as $prefix) {
            if (strpos($message, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
}