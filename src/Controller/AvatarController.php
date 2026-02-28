<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/avatar')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AvatarController extends AbstractController
{
    // --- AI VISION (Image-to-Text) ---
    // Groq free tier — llama-4-scout has vision, no payment required
    private const GROQ_VISION_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_VISION_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    // --- AI GENERATION (Text-to-Image) ---
    // Pollinations.ai — completely free, no API key needed
    private const POLLINATIONS_URL = 'https://image.pollinations.ai/prompt/';

    private function getHfToken(): string
    {
        return $_SERVER['HUGGINGFACE_TOKEN']
            ?? $_ENV['HUGGINGFACE_TOKEN']
            ?? (getenv('HUGGINGFACE_TOKEN') ?: '');
    }

    private const STYLE_PROMPTS = [
        'anime'     => 'anime style portrait of [DESC], Studio Ghibli art style, detailed face, vibrant colors, soft shading, beautiful illustration, high quality',
        'cartoon'   => 'cartoon portrait of [DESC], Pixar 3D animation style, expressive face, clean bright colors, professional character design, high quality',
        'manga'     => 'manga portrait of [DESC], black and white ink drawing, detailed linework, shounen manga art style, professional illustration',
        'pixel'     => 'pixel art portrait of [DESC], 16-bit retro game character, colorful, detailed pixel art, RPG game sprite style',
        'fantasy'   => 'fantasy portrait of [DESC], epic digital painting, magical atmosphere, detailed face, professional concept art, vibrant dramatic colors',
        'realistic' => 'hyperrealistic portrait of [DESC], professional photography, sharp details, beautiful studio lighting, high quality, photorealistic',
    ];

    // ========== DESCRIBE PHOTO ==========

    #[Route('/describe', name: 'avatar_describe', methods: ['POST'])]
    public function describe(Request $request, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('avatar_describe', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $file = $request->files->get('photo');
        if (!$file) {
            return new JsonResponse(['error' => 'No photo uploaded'], 400);
        }

        $result = $this->analyzeImage($file, $logger);

        if (isset($result['error']) && !empty($result['model_loading'])) {
            return new JsonResponse($result, 503);
        }

        return new JsonResponse($result);
    }

    /**
     * Analyze image using Groq free vision API (llama-4-scout).
     * Uses GROQ_API_KEY already in .env — completely free, no payment needed.
     */
    private function analyzeImage(UploadedFile $file, LoggerInterface $logger): array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            return ['error' => 'Invalid file type. Use JPG, PNG or WEBP.', 'success' => false];
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return ['error' => 'File too large. Max 5MB.', 'success' => false];
        }

        $groqKey = $_SERVER['GROQ_API_KEY'] ?? $_ENV['GROQ_API_KEY'] ?? (getenv('GROQ_API_KEY') ?: '');
        if (empty($groqKey)) {
            $logger->error('[Vision] GROQ_API_KEY missing in .env');
            return ['error' => 'GROQ_API_KEY not configured', 'success' => false];
        }

        $base64  = base64_encode(file_get_contents($file->getPathname()));
        $dataUri = 'data:' . $mime . ';base64,' . $base64;

        $logger->info('[Vision] ---> Calling Groq llama-4-scout vision...');

        $payload = json_encode([
            'model'      => self::GROQ_VISION_MODEL,
            'max_tokens' => 150,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                    ['type' => 'text', 'text' => 'Describe the person in this photo in ONE short sentence for an avatar prompt. Focus on: gender, age, hair color and style, eye color, skin tone, distinctive features (glasses, beard, etc.), expression. Example: "young woman with long black hair, brown eyes, light skin, wearing glasses, smiling". Reply with ONLY the description, nothing else.'],
                ],
            ]],
        ]);

        $ch = curl_init(self::GROQ_VISION_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $groqKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $logger->info('[Vision] Groq HTTP=' . $httpCode);

        if ($httpCode === 200 && !$curlErr) {
            $data    = json_decode((string)$response, true);
            $caption = $data['choices'][0]['message']['content'] ?? '';
            $caption = $this->cleanCaption($caption);
            if (strlen($caption) > 10) {
                $logger->info('[Vision] Groq WINNER: ' . $caption);
                return ['success' => true, 'description' => $caption, 'source' => 'groq-vision'];
            }
        }

        $body   = json_decode((string)$response, true);
        $errMsg = $body['error']['message'] ?? ('HTTP ' . $httpCode);
        $logger->warning('[Vision] Groq failed: ' . $errMsg);
        return ['success' => false, 'error' => 'Vision failed: ' . $errMsg, 'source' => 'none'];
    }
    private function cleanCaption(string $caption): string
    {
        $caption = trim(strip_tags($caption));
        $caption = trim($caption, '"\'');
        // Remove common AI prefaces
        $caption = preg_replace('/^(description:|here is|the person is|i see|a photo of|an image of)/i', '', $caption);
        return trim($caption);
    }

    // ========== GENERATE AVATAR ==========

    #[Route('/generate', name: 'avatar_generate', methods: ['POST'])]
    public function generate(Request $request, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('avatar_generate', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $description = trim($request->request->get('description', ''));
        /** @var UploadedFile|null $file */
        $file = $request->files->get('photo');

        if (!$description && $file) {
            $logger->info('[Avatar/Generate] No description provided, triggering Vision extraction...');
            $result = $this->analyzeImage($file, $logger);
            
            if (!empty($result['description'])) {
                $description = $result['description'];
                $logger->info('[Avatar/Generate] Vision extraction success (Source: ' . ($result['source'] ?? 'unknown') . '): ' . $description);
            }
        }

        if (strlen($description) < 3) {
            return new JsonResponse(['error' => 'Please provide a description (minimum 3 characters) or upload a photo.'], 400);
        }

        $style = $request->request->get('style', 'anime');
        $template = self::STYLE_PROMPTS[$style] ?? self::STYLE_PROMPTS['anime'];
        $prompt = str_replace('[DESC]', $description, $template);

        $logger->info('[Avatar] Style=' . $style . ' | Prompt=' . $prompt);

        // Try image generation — Pollinations first, then Stable Horde as fallback
        $imageData = null;
        $lastErr   = '';

        // --- Option 1: Pollinations.ai (free, no key) ---
        $encodedPrompt   = urlencode($prompt);
        $seed            = rand(1, 999999);
        $pollinationsUrl = self::POLLINATIONS_URL . $encodedPrompt
            . '?width=512&height=512&seed=' . $seed . '&nologo=true&model=flux&enhance=false';

        $logger->info('[Avatar] Trying Pollinations...');
        $ch = curl_init($pollinationsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
                'Accept: image/webp,image/png,image/*,*/*',
                'Referer: https://pollinations.ai/',
            ],
        ]);
        $imageData = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        curl_close($ch);
        $logger->info('[Avatar] Pollinations HTTP=' . $httpCode . ' size=' . strlen((string)$imageData));

        if ($curlErr || $httpCode !== 200 || strlen((string)$imageData) < 500) {
            $lastErr   = $curlErr ?: ('HTTP ' . $httpCode);
            $imageData = null;
            $logger->warning('[Avatar] Pollinations failed: ' . $lastErr . ', trying Stable Horde...');

            // --- Option 2: Stable Horde (free, no key needed with anon key) ---
            $hordePayload = json_encode([
                'prompt'  => $prompt,
                'params'  => ['width' => 512, 'height' => 512, 'steps' => 20, 'n' => 1],
                'models'  => ['Deliberate'],
                'r2'      => true,
                'shared'  => true,
                'trusted_workers' => false,
            ]);

            // Step 1: Submit job
            $ch2 = curl_init('https://stablehorde.net/api/v2/generate/async');
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $hordePayload,
                CURLOPT_HTTPHEADER     => [
                    'apikey: 0000000000',
                    'Content-Type: application/json',
                    'Client-Agent: bekri-avatar:1.0:contact@bekri.com',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $hordeResp = curl_exec($ch2);
            $hordeCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            $logger->info('[Avatar] Horde submit HTTP=' . $hordeCode);

            $jobId = json_decode((string)$hordeResp, true)['id'] ?? null;

            if ($jobId) {
                // Step 2: Poll for result (max 90s)
                $maxTries = 18;
                for ($i = 0; $i < $maxTries; $i++) {
                    sleep(5);
                    $ch3 = curl_init('https://stablehorde.net/api/v2/generate/status/' . $jobId);
                    curl_setopt_array($ch3, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => ['apikey: 0000000000', 'Client-Agent: bekri-avatar:1.0:contact@bekri.com'],
                        CURLOPT_TIMEOUT        => 15,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    $statusResp = curl_exec($ch3);
                    curl_close($ch3);
                    $status = json_decode((string)$statusResp, true);
                    $logger->info('[Avatar] Horde poll ' . ($i+1) . ' done=' . ($status['done'] ? 'true' : 'false'));

                    if (!empty($status['done'])) {
                        $imgUrl = $status['generations'][0]['img'] ?? null;
                        if ($imgUrl) {
                            // Download the image
                            $ch4 = curl_init($imgUrl);
                            curl_setopt_array($ch4, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
                            $imageData = curl_exec($ch4);
                            curl_close($ch4);
                            $logger->info('[Avatar] Horde image downloaded: ' . strlen((string)$imageData) . ' bytes');
                        }
                        break;
                    }
                }
            }

            if ($imageData === null || strlen((string)$imageData) < 500) {
                return new JsonResponse(['error' => 'Image generation failed. Both Pollinations (' . $lastErr . ') and Stable Horde unavailable. Please retry.'], 500);
            }
        }

        // Validate image
        $isPng = substr($imageData, 0, 4) === "\x89PNG";
        $isJpeg = substr($imageData, 0, 2) === "\xFF\xD8";
        $isWebp = substr($imageData, 8, 4) === 'WEBP';

        if (!$isPng && !$isJpeg && !$isWebp) {
            $body = json_decode((string)$imageData, true);
            $msg = $body['error'] ?? 'Invalid image data received';
            $logger->error('[Avatar] Not a valid image: ' . $msg);
            return new JsonResponse(['error' => $msg . ' — Please retry.'], 500);
        }

        $uploadDir = $this->getParameter('avatars_directory') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = $isPng ? 'png' : ($isWebp ? 'webp' : 'jpg');
        $filename = 'avatar_ai_' . uniqid() . '.' . $ext;
        file_put_contents($uploadDir . $filename, $imageData);

        $logger->info('[Avatar] Saved: ' . $filename . ' (' . strlen($imageData) . ' bytes)');

        return new JsonResponse([
            'success' => true,
            'avatar_url' => '/uploads/avatars/' . $filename,
        ]);
    }

    // ========== SAVE AS PROFILE PICTURE ==========

    #[Route('/save', name: 'avatar_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isCsrfTokenValid('avatar_save', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid token'], 403);
        }

        $avatarUrl = $request->request->get('avatar_url');
        if (!$avatarUrl || !str_starts_with($avatarUrl, '/uploads/avatars/')) {
            return new JsonResponse(['error' => 'Invalid avatar URL'], 400);
        }

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $uploadDir = $this->getParameter('avatars_directory') . '/';
        $filename = basename($avatarUrl);
        $localPath = $uploadDir . $filename;

        if (!file_exists($localPath)) {
            return new JsonResponse(['error' => 'File not found'], 404);
        }

        // Delete old avatar
        $oldAvatar = $user->getAvatar();
        if ($oldAvatar && !str_starts_with($oldAvatar, 'avatar_ai_') && file_exists($uploadDir . $oldAvatar)) {
            unlink($uploadDir . $oldAvatar);
        }

        // Clean up old AI temp files
        foreach (glob($uploadDir . 'avatar_ai_*') as $tmpFile) {
            if ($tmpFile !== $localPath && filemtime($tmpFile) < time() - 3600) {
                unlink($tmpFile);
            }
        }

        $user->setAvatar($filename);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $logger->info('[Avatar] Profile picture updated for user: ' . $user->getEmail());

        return new JsonResponse([
            'success' => true,
            'avatar' => $filename,
            'avatar_url' => '/uploads/avatars/' . $filename
        ]);
    }
    
    #[Route('/generator', name: 'avatar_generator')]
    public function generator(): Response
    {
        return $this->render('user/avatar_generator.html.twig');
    }
}
