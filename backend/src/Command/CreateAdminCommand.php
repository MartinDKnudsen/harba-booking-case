<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create default admin user')]
class CreateAdminCommand extends Command
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = 'admin@demo.com';
        $plainPassword = 'admin123';

        $repo = $this->em->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);

        if ($existing) {
            $output->writeln("");
            $output->writeln(" <fg=yellow;options=bold>⚠ Admin user already exists</>");
            $output->writeln(" <fg=cyan>Email:</>   <options=bold>$email</>");
            $output->writeln(" <fg=cyan>Login:</>  Use your existing password\n");

            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln("");
        $output->writeln(" <fg=green;options=bold>✔ Admin User Created</>");
        $output->writeln(" <fg=white;bg=blue;options=bold>   LOGIN DETAILS   </>");
        $output->writeln("");
        $output->writeln(" <fg=cyan>Email:</>      <options=bold>$email</>");
        $output->writeln(" <fg=cyan>Password:</>   <options=bold>$plainPassword</>");
        $output->writeln("");
        $output->writeln(" <fg=green;options=bold>You can now log in as an administrator.</>");
        $output->writeln("");

        return Command::SUCCESS;
    }
}
