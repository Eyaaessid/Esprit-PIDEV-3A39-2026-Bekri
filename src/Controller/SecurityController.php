<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage
    ) {}

    // ==================== LOGIN ====================
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // If user is already logged in → redirect based on role
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getRedirectRouteByRole());
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $loginAttempts = $request->getSession()->get('login_attempts', 0);
        $captcha = $this->generateWellnessCaptcha($request);

        return $this->render('admin/signin.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'login_attempts' => $loginAttempts,
            'captcha_question' => $captcha['question'],
            'show_captcha' => $loginAttempts >= 2,
        ]);
    }

    // ==================== LOGOUT ====================
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // ==================== REGISTER ====================
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerifier $emailVerifier
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getRedirectRouteByRole());
        }

        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // CAPTCHA validation: use session from when form was displayed (do NOT regenerate before validate)
            $captchaError = $this->validateCaptcha($request);
            if ($captchaError !== null) {
                $this->addFlash('error', $captchaError);
                $newCaptcha = $this->generateWellnessCaptcha($request);
                return $this->render('admin/signup.html.twig', [
                    'registrationForm' => $form->createView(),
                    'captcha_question' => $newCaptcha['question'],
                ]);
            }

            $existingUser = $entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $user->getEmail()]);

            if ($existingUser) {
                $this->addFlash('error', 'Cet email est déjà utilisé. Vous avez déjà un compte ? <a href="' . $this->generateUrl('app_login') . '" class="alert-link">Connectez-vous ici</a>.');
                $freshCaptcha = $this->generateWellnessCaptcha($request);
                return $this->render('admin/signup.html.twig', [
                    'registrationForm' => $form->createView(),
                    'captcha_question' => $freshCaptcha['question'],
                ]);
            }
            
            // ✅ Email unique → continuer avec l'inscription
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setRole(UtilisateurRole::USER);
            $user->setStatut(UtilisateurStatut::ACTIF);
            $user->setIsVerified(false);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->logger->info('New user registered', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value
            ]);

            try {
                $email = (new TemplatedEmail())
                    ->from(EmailVerifier::defaultFrom())
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Verify your email address')
                    ->htmlTemplate('email/verification.html.twig')
                    ->context([
                        'user' => $user,
                        'supportEmail' => 'support@bekri.com',
                    ]);

                $emailVerifier->sendEmailConfirmation('app_verify_email', $user, $email);
                $this->addFlash('success', 'Check your email to verify your account');

            } catch (\Exception $e) {
                // Log error but don't prevent registration
                $this->logger->error('Failed to send verification email', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'error' => $e->getMessage()
                ]);

            }

            // Removed auto-login (setToken) to force email verification flow
            $this->addFlash('info', 'Please verify your email before logging in.');
            return $this->redirectToRoute('app_check_email', ['email' => $user->getEmail()]);
        }

        // Display form (GET or invalid): generate captcha so session has answer when user submits
        $captcha = $this->generateWellnessCaptcha($request);
        return $this->render('admin/signup.html.twig', [
            'registrationForm' => $form->createView(),
            'captcha_question' => $captcha['question'],
        ]);
    }

    // ==================== CHECK EMAIL (VERIFY) ====================
    #[Route('/register/check-email', name: 'app_check_email')]
    public function checkEmail(Request $request): Response
    {
        return $this->render('security/check_email.html.twig', [
            'email' => (string) $request->query->get('email', ''),
        ]);
    }

    // ==================== CAPTCHA HELPERS ====================

    /**
     * Generate a wellness-themed math captcha. Stores answer in session, returns question.
     */
    private function generateWellnessCaptcha(Request $request): array
    {
        $variants = [
            ['q' => 'Si votre score bien-être est %d et vous progressez de %d, quel est votre nouveau score ?', 'a' => fn($x, $y) => $x + $y, 'r' => [50, 90, 5, 25]],
            ['q' => 'Vous marchez %d min par jour. Cette semaine vous ajoutez %d min. Total en min ?', 'a' => fn($x, $y) => $x + $y, 'r' => [20, 60, 10, 30]],
            ['q' => 'Vous buvez %d verres d\'eau. Vous en ajoutez %d. Combien au total ?', 'a' => fn($x, $y) => $x + $y, 'r' => [4, 8, 2, 5]],
            ['q' => 'Score du jour : %d. Vous gagnez %d points. Nouveau score ?', 'a' => fn($x, $y) => $x + $y, 'r' => [30, 70, 5, 20]],
            ['q' => 'Objectif : %d séances par semaine. Vous en faites %d de plus. Total ?', 'a' => fn($x, $y) => $x + $y, 'r' => [2, 5, 1, 3]],
        ];
        $v = $variants[array_rand($variants)];
        $num1 = random_int($v['r'][0], $v['r'][1]);
        $num2 = random_int($v['r'][2], $v['r'][3]);
        $question = sprintf($v['q'], $num1, $num2);
        $answer = $v['a']($num1, $num2);
        $request->getSession()->set('captcha_answer', (string) $answer);
        return ['question' => $question, 'answer' => $answer];
    }

    /**
     * Validate honeypot, timestamp and math captcha. Returns error message or null.
     */
    private function validateCaptcha(Request $request): ?string
    {
        if (trim((string) $request->request->get('website', '')) !== '') {
            return 'Requête invalide.';
        }
        $ts = (int) $request->request->get('form_timestamp', 0);
        $now = time();
        if ($now - $ts < 3) {
            return 'Veuillez patienter quelques secondes avant d\'envoyer le formulaire.';
        }
        $userAnswer = trim((string) $request->request->get('captcha_answer', ''));
        $correctAnswer = $request->getSession()->get('captcha_answer');
        if ($correctAnswer === null || $userAnswer !== (string) $correctAnswer) {
            return 'Vérification de sécurité incorrecte. Veuillez réessayer.';
        }
        $request->getSession()->remove('captcha_answer');
        return null;
    }

    // ==================== HELPER METHOD ====================
    private function getRedirectRouteByRole(): string
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return 'admin_dashboard';
        }

        if ($this->isGranted('ROLE_COACH')) {
            return 'home';
        }

        // Normal users (role USER) go to the public homepage
        return 'home';
    }
}