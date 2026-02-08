<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter last name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter last name']),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter first name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter first name']),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control', 'placeholder' => 'user@example.com'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter email']),
                    new Email(['message' => 'Please enter a valid email address']),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '+216 XX XXX XXX'],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date of Birth',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter date of birth']),
                ],
            ])
            ->add('pays', TextType::class, [
                'label' => 'Country',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Tunisia'],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Role',
                'attr' => ['class' => 'form-select'],
                'choices' => [
                    'User' => UtilisateurRole::USER,
                    'Coach' => UtilisateurRole::COACH,
                    'Administrator' => UtilisateurRole::ADMIN,
                ],
                'choice_label' => function (UtilisateurRole $role) {
                    return $role->label();
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
                    return $statut->label();
                },
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPG, PNG, GIF, or WebP)',
                    ])
                ],
            ]);

        if (!$isEdit) {
            // New user - password is required
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Minimum 6 characters'],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Repeat the password'],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a password']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long',
                        'max' => 4096,
                    ]),
                ],
            ]);
        } else {
            // Edit user - password is optional
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'New Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Leave blank to keep current password'],
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Repeat the new password'],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long',
                        'max' => 4096,
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'is_edit' => false,
        ]);
    }
}