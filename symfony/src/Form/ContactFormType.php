<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * The contact form as a whole. The controller names it "data" and the message
 * fields sit under "Message", so the fields are named data[Message][name] and
 * so on, exactly as in Croogo's form. Croogo's links and the characterization
 * tests keep working without knowing which app answers.
 *
 * @extends AbstractType<array{Message: \App\Entity\Message}>
 */
final class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('Message', MessageType::class, [
            // Validate the Message entity with its own constraints.
            'constraints' => [new Valid()],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // A token id outside config/packages/csrf.yaml's stateless list,
            // so this form uses the classic CSRF token kept in the session.
            'csrf_token_id' => 'contact_message',
        ]);
    }
}
