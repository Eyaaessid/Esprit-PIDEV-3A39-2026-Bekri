<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
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
use Symfony\Component\Validator\Constraints\Regex;
use App\Validator\Constraints\RealEmail;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter your first name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your first name']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'First name must be at least {{ limit }} characters',
                        'maxMessage' => 'First name cannot exceed {{ limit }} characters'
                    ]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter your last name'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your last name']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Last name must be at least {{ limit }} characters',
                        'maxMessage' => 'Last name cannot exceed {{ limit }} characters'
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control', 'placeholder' => 'your.email@example.com'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your email']),
                    new Email([
                        'message' => 'Please enter a valid email address',
                        'mode' => 'strict',
                    ]),
                    new RealEmail([
                        'message' => 'This email domain cannot receive emails.',
                        'disposableMessage' => 'Disposable email addresses are not allowed.',
                        'typoMessage' => 'Did you mean {{ suggestion }} instead of {{ value }}?',
                    ]),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '+216 XX XXX XXX'],
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'max' => 20,
                        'minMessage' => 'Phone number must be at least {{ limit }} digits',
                        'maxMessage' => 'Phone number cannot exceed {{ limit }} characters'
                    ]),
                    new Regex([
                        'pattern' => '/^[+]?[0-9\s\-()]+$/',
                        'message' => 'Please enter a valid phone number using only digits, spaces, +, - or ()'
                    ]),
                ],
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date of Birth',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
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
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'New Password',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Leave blank to keep current password'
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Repeat the new password'
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long',
                        'max' => 4096,
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.'
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            // This allows the form to update the current user without unique constraint issues on email
            'validation_groups' => ['Default'],
        ]);
    }
}