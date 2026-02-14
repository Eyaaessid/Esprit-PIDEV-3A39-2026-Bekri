<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserEditFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', ChoiceType::class, [
                'label' => 'Role',
                'attr' => ['class' => 'form-select'],
                'choices' => [
                    'User' => UtilisateurRole::USER,
                    'Coach' => UtilisateurRole::COACH,
                    'Administrator' => UtilisateurRole::ADMIN,
                ],
                'choice_label' => function (UtilisateurRole $role) {
                    return $role->getLabel();
                },
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Status',
                'attr' => ['class' => 'form-select'],
                'choices' => [
                    'Active' => UtilisateurStatut::ACTIF,
                    'Blocked' => UtilisateurStatut::BLOQUE,
                    'Inactive' => UtilisateurStatut::INACTIF,
                ],
                'choice_label' => function (UtilisateurStatut $statut) {
                    return $statut->getLabel();
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
