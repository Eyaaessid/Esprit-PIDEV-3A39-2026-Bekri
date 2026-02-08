<?php

namespace App\Form;

use App\Entity\ObjectifBienEtre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObjectifBienEtreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', null, [
                'label' => 'Titre de l\'objectif',
                'attr' => ['placeholder' => 'ex: Perdre 5 kg en 3 mois']
            ])
            ->add('description', null, [
                'required' => false,
                'label' => 'Description (facultatif)'
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Sommeil'     => 'sommeil',
                    'Poids'       => 'poids',
                    'Nutrition'   => 'nutrition',
                    'Activité physique' => 'activite',
                    'Hydratation' => 'hydratation',
                    'Autres'      => 'autres',
                ],
                'placeholder' => 'Sélectionnez un type d\'objectif',
                'label' => 'Type d\'objectif'
            ])
            ->add('valeurCible', null, [
                'label' => 'Valeur cible',
                'help'  => 'ex: 8 pour 8h de sommeil, 65 pour 65 kg, etc.'
            ])
            ->add('valeurActuelle', null, [
                'required' => false,
                'label' => 'Valeur actuelle (optionnel au début)'
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
                'html5' => true,
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'html5' => true,
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'En cours'   => 'en_cours',
                    'Atteint'    => 'atteint',
                    'Abandonné'  => 'abandonne',
                ],
                'placeholder' => 'Choisir le statut',
                'label' => 'Statut actuel'
            ])

            // ────────────────────────────────────────────────
            // → NO createdAt, updatedAt, utilisateur fields here
            // ────────────────────────────────────────────────
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ObjectifBienEtre::class,
        ]);
    }
}