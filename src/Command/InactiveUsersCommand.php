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
 * Mark ACTIF users as INACTIF when they have not logged in for 30+ days.
 * Run daily via cron: php bin/console app:mark-inactive-users
 */
#[AsCommand(
    name: 'app:mark-inactive-users',
    description: 'Mark users inactive when no login for 30+ days and send notification email',
)]
class InactiveUsersCommand extends Command
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
        $threshold = new \DateTime('-30 days');

        $qb = $this->entityManager->getRepository(Utilisateur::class)->createQueryBuilder('u');
        $qb->where('u.statut = :actif')->setParameter('actif', UtilisateurStatut::ACTIF);
        $qb->andWhere($qb->expr()->orX(
            'u.lastLoginAt IS NULL',
            'u.lastLoginAt < :threshold'
        ))->setParameter('threshold', $threshold);
        $users = $qb->getQuery()->getResult();

        $count = 0;
        foreach ($users as $user) {
            if ($user->getRole()->value === 'admin') {
                continue;
            }
            $user->setStatut(UtilisateurStatut::INACTIF);
            $user->setDeactivatedAt(new \DateTime());
            $user->setDeactivatedBy('system');
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Votre compte a été marqué inactif')
                    ->htmlTemplate('emails/account_marked_inactive.html.twig')
                    ->context(['user' => $user]);
                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $io->warning("Could not send email to {$user->getEmail()}: " . $e->getMessage());
            }
            $count++;
        }

        $io->success("Marked {$count} user(s) as inactive.");
        return Command::SUCCESS;
    }
}
