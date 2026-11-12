<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The four fields of a contact message, bound to the Message entity: the form
 * writes what was submitted into it, and the entity's constraints validate it.
 *
 * Labels and sizes are Croogo's. "required" => false only leaves out the HTML
 * required attribute, which Croogo's form doesn't have either: visitors see
 * the same server-side messages instead of the browser's own pop-ups. The
 * email field is plain text for the same reason.
 *
 * @extends AbstractType<Message>
 */
final class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Your name',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('email', TextType::class, [
                'label' => 'Your email',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('title', TextType::class, [
                'label' => 'Subject',
                'required' => false,
                'attr' => ['maxlength' => 255],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
                'required' => false,
                'attr' => ['cols' => 30, 'rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Message::class]);
    }
}
