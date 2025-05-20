<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AiSummaryService
{
    private $httpClient;
    private $params;
    private $retryAttempts = 2; // Number of retry attempts for recoverable errors
    private $retryDelay = 2; // Base delay in seconds between retries

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->params = $params;
    }

    public function generateSummary(string $title, string $author, string $description): string
    {
        // Check if API key is set
        $apiKey = $this->params->get('openai_api_key');
        if (empty($apiKey)) {
            throw new \Exception('OpenAI API key is not configured. Please set the OPENAI_API_KEY in your .env file.');
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
                    $responseBody = $response->getContent(false);
                    $responseData = json_decode($responseBody, true);
                    
                    // Check if there's a retry-after header or value in the response
                    $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? 
                                  ($responseData['error']['retry_after'] ?? null);
                    
                    if ($retryAfter && $attempts < $this->retryAttempts) {
                        // Wait for the specified time and then retry
                        sleep(intval($retryAfter));
                        $attempts++;
                        continue;
                    }
                    
                    throw new \Exception('Rate limit exceeded. Please try again later or check your OpenAI API quota.');
                } else {
                    // Handle other error status codes
                    $errorMessage = $this->getErrorMessageForStatusCode($statusCode, $response);
                    throw new \Exception($errorMessage);
                }
                
            } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
                // Network-related errors
                if ($attempts < $this->retryAttempts) {
                    $attempts++;
                    continue;
                }
                
                error_log('OpenAI API Transport Error: ' . $e->getMessage());
                return 'Unable to generate AI summary: Network error when connecting to OpenAI API. Please try again later.';
            } catch (\Exception $e) {
                // Log the error
                error_log('OpenAI API Error: ' . $e->getMessage());
                
                // Only retry on certain exceptions
                if (($e->getMessage() === 'Rate limit exceeded. Please try again later or check your OpenAI API quota.') && 
                    ($attempts < $this->retryAttempts)) {
                    $attempts++;
                    continue;
                }
                
                // Return a more helpful error message
                return 'Unable to generate AI summary: ' . $e->getMessage();
            }
        }
        
        // If we've exhausted all retries
        return 'Unable to generate AI summary after multiple attempts. Please try again later.';
    }
    
    private function getErrorMessageForStatusCode(int $statusCode, $response): string
    {
        try {
            $responseBody = $response->getContent(false);
            $responseData = json_decode($responseBody, true);
            $errorMessage = $responseData['error']['message'] ?? null;
        } catch (\Exception $e) {
            $errorMessage = null;
        }
        
        switch ($statusCode) {
            case 400:
                return 'Bad request to OpenAI API: ' . ($errorMessage ?? 'Invalid request parameters');
            case 401:
                return 'Authentication error with OpenAI API: ' . ($errorMessage ?? 'API key may be invalid');
            case 403:
                return 'Forbidden request to OpenAI API: ' . ($errorMessage ?? 'You do not have permission to use this endpoint');
            case 404:
                return 'OpenAI API endpoint not found: ' . ($errorMessage ?? 'The requested resource does not exist');
            case 429:
                return 'Rate limit exceeded: ' . ($errorMessage ?? 'Too many requests to OpenAI API');
            case 500:
            case 502:
            case 503:
            case 504:
                return 'OpenAI API server error: ' . ($errorMessage ?? 'Service currently unavailable');
            default:
                return 'OpenAI API returned status code: ' . $statusCode . ($errorMessage ? ' - ' . $errorMessage : '');
        }
    }
}