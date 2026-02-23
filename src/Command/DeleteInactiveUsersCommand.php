<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

/**
 * Permanently delete users who have been INACTIF for 365+ days (GDPR).
 * Optionally send warning at day 355. Run daily: php bin/console app:delete-old-inactive-users
 */
#[AsCommand(
    name: 'app:delete-old-inactive-users',
    description: 'Delete users inactive for 365+ days and send final notification',
)]
class DeleteInactiveUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $threshold = new \DateTime('-365 days');

        $users = $this->entityManager->getRepository(Utilisateur::class)->createQueryBuilder('u')
            ->where('u.statut = :inactif')->setParameter('inactif', UtilisateurStatut::INACTIF)
            ->andWhere('u.deactivatedAt < :threshold')->setParameter('threshold', $threshold)
            ->getQuery()->getResult();

        $count = 0;
        foreach ($users as $user) {
            if ($user->getRole()->value === 'admin') {
                continue;
            }
            $email = $user->getEmail();
            $name = $user->getPrenom() . ' ' . $user->getNom();
            try {
                $finalEmail = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($email, $name))
                    ->subject('Votre compte Bekri a été supprimé')
                    ->htmlTemplate('emails/account_deleted.html.twig')
                    ->context(['userName' => $name]);
                $this->mailer->send($finalEmail);
            } catch (\Throwable $e) {
                $io->warning("Could not send deletion email to {$email}: " . $e->getMessage());
            }
            $this->entityManager->remove($user);
            $count++;
        }
        $this->entityManager->flush();

        $io->success("Deleted {$count} inactive user(s).");
        return Command::SUCCESS;
    }
}
