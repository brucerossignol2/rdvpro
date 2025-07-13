<?php
// src/Form/RegistrationFormType.php
namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType; // For business phone
use Symfony\Component\Form\Extension\Core\Type\TextareaType; // For business address
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex; // For phone number validation

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => ['autocomplete' => 'email', 'class' => 'form-control'],
                'label' => 'Adresse Email',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une adresse email',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre email doit contenir au moins {{ limit }} caractères',
                        'max' => 180,
                    ]),
                ],
            ])
            ->add('firstName', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer votre prénom.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer votre nom.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('businessName', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Nom de votre entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer le nom de votre entreprise.']),
                    new Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('businessAddress', TextareaType::class, [
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'label' => 'Adresse de votre entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer l\'adresse de votre entreprise.']),
                    new Length(['min' => 10, 'max' => 255]),
                ],
            ])
            ->add('businessPhone', TelType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Téléphone de l\'entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer le numéro de téléphone de votre entreprise.']),
                    new Regex([
                        'pattern' => '/^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:[\s.-]?\d{2}){4}$/',
                        'message' => 'Veuillez entrer un numéro de téléphone valide (format français).',
                    ]),
                ],
            ])
            ->add('businessEmail', EmailType::class, [
                'attr' => ['autocomplete' => 'email', 'class' => 'form-control'],
                'label' => 'Email de contact de l\'entreprise',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un email de contact pour l\'entreprise.']),
                    new Length(['min' => 6, 'max' => 100]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J\'accepte les conditions d\'utilisation',
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter nos conditions d\'utilisation.',
                    ]),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control'],
                'label' => 'Mot de passe',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
