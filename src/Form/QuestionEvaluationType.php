<?php

namespace App\Form;

use App\Entity\QuestionEvaluation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert; 

class QuestionEvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('texte', null, [
                'label' => 'Texte de la question',
                'attr' => [
                    'placeholder' => 'Exemple : Comment vous sentez-vous aujourd\'hui ?',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: "Le texte de la question est obligatoire."),
                    new Assert\Length(
                        min: 5,
                        max: 255,
                        minMessage: "La question doit contenir au moins {{ limit }} caractères.",
                        maxMessage: "La question ne peut pas dépasser {{ limit }} caractères."
                    ),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
                'choices' => [
                    'Humeur'            => 'humeur',
                    'Sommeil'           => 'sommeil',
                    'Poids'             => 'poids',
                    'Nutrition'         => 'nutrition',
                    'Activité physique' => 'activite',
                    'Hydratation'       => 'hydratation',
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition',
                ],
            ])
            ->add('option1', TextType::class, [
                'label' => 'Option 1',
                'attr' => [
                    'placeholder' => 'Exemple : Très bien 😊',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition',
                ],
                
            ])
            ->add('option2', TextType::class, [
                'label' => 'Option 2',
                'attr' => [
                    'placeholder' => 'Exemple : Correct 🙂',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition',
                ],
                
            ])
            ->add('option3', TextType::class, [
                'label' => 'Option 3',
                'attr' => [
                    'placeholder' => 'Exemple : Pas terrible 😔',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none transition',
                ],
                
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuestionEvaluation::class,
        ]);
    }
}