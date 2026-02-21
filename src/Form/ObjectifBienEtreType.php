<?php

namespace App\Form;

use App\Entity\ObjectifBienEtre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ObjectifBienEtreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'objectif',
                'attr'  => ['placeholder' => 'ex: Perdre 5 kg en 3 mois'],
                'constraints' => [
                    new Assert\NotBlank(message: "Le titre est obligatoire."),
                    new Assert\Length(
                        min: 3,
                        max: 150,
                        minMessage: "Le titre doit contenir au moins {{ limit }} caractères.",
                        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères."
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label'    => 'Description (facultatif)',
                'attr'     => ['placeholder' => 'Décrivez votre objectif en détail...', 'rows' => 3],
                'constraints' => [
                    new Assert\Length(
                        max: 500,
                        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
                    ),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Humeur'            => 'humeur',
                    'Sommeil'           => 'sommeil',
                    'Poids'             => 'poids',
                    'Nutrition'         => 'nutrition',
                    'Activité physique' => 'activite',
                    'Hydratation'       => 'hydratation',
                ],
                'placeholder' => 'Sélectionnez un type d\'objectif',
                'label'       => 'Type d\'objectif',
                'constraints' => [
                    new Assert\NotBlank(message: "Le type d'objectif est obligatoire."),
                    new Assert\Choice(
                        choices: ['humeur', 'sommeil', 'poids', 'nutrition', 'activite', 'hydratation'],
                        message: "Type d'objectif invalide."
                    ),
                ],
            ])
            ->add('valeurCible', NumberType::class, [
                'label' => 'Valeur cible',
                'help'  => 'ex: 8 pour 8h de sommeil, 65 pour 65 kg, etc.',
                'constraints' => [
                    new Assert\NotBlank(message: "La valeur cible est obligatoire."),
                    new Assert\Positive(message: "La valeur cible doit être un nombre positif."),
                    new Assert\LessThanOrEqual(
                        value: 9999,
                        message: "La valeur cible ne peut pas dépasser {{ compared_value }}."
                    ),
                ],
            ])
            ->add('valeurActuelle', NumberType::class, [
                'required' => false,
                'label'    => 'Valeur actuelle (optionnel au début)',
                'constraints' => [
                    new Assert\PositiveOrZero(message: "La valeur actuelle ne peut pas être négative."),
                    new Assert\LessThanOrEqual(
                        value: 9999,
                        message: "La valeur actuelle ne peut pas dépasser {{ compared_value }}."
                    ),
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label'  => 'Date de début',
                'html5'  => true, // ← calendar picker enabled, Assert handles server-side validation
                'attr'   => [
                    'min' => (new \DateTime('today'))->format('Y-m-d'), // blocks past dates in calendar UI
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "La date de début est obligatoire."),
                    new Assert\GreaterThanOrEqual(
                        value: 'today',
                        message: "La date de début ne peut pas être dans le passé."
                    ),
                ],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'label'  => 'Date de fin',
                'html5'  => true, // ← calendar picker enabled, Assert handles server-side validation
                'attr'   => [
                    'min' => (new \DateTime('tomorrow'))->format('Y-m-d'), // blocks today and past in calendar UI
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "La date de fin est obligatoire."),
                    new Assert\GreaterThan(
                        value: 'today',
                        message: "La date de fin doit être dans le futur."
                    ),
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'En cours'  => 'en_cours',
                    'Atteint'   => 'atteint',
                    'Abandonné' => 'abandonne',
                ],
                'placeholder' => 'Choisir le statut',
                'label'       => 'Statut actuel',
                'constraints' => [
                    new Assert\NotBlank(message: "Le statut est obligatoire."),
                    new Assert\Choice(
                        choices: ['en_cours', 'atteint', 'abandonne'],
                        message: "Statut invalide."
                    ),
                ],
            ]);

        // ── Cross-field validation: dateFin must be after dateDebut ──
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();

            $dateDebut = $form->get('dateDebut')->getData();
            $dateFin   = $form->get('dateFin')->getData();

            if ($dateDebut && $dateFin && $dateFin <= $dateDebut) {
                $form->get('dateFin')->addError(
                    new FormError("La date de fin doit être postérieure à la date de début.")
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ObjectifBienEtre::class,
        ]);
    }
}