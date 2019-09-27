<?php
/**
 * @file
 * Contains \Drupal\emerging_technology\Form\ETPrintForm
 */
namespace Drupal\emerging_technology\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Render\Element\Ajax;

use Drupal\Core\Ajax\AjaxResponse;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * 3D Print Request form.
 */

class ETPrintForm extends FormBase implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'et_print_form';
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();

    if(!$user->isAnonymous()) {
      # Note:  MacEwan uses an in-house authentication system from which we can pull student data on login.  We use
      #        this to populate institutional Person ID as well as email and first and last name.  The following is
      #        an example using drupal user account information instead.
      $person_id = $user->id();
      $email = $user->getEmail();
      $fname = $user->getDisplayName();
      $lname = '';

      $user_info = '<p><strong>'.$fname.' '.$lname.'</strong><br />'.$email.'</p>'; 

      # Search for existing request(s).  Since this service was first rolled out free of charge, it was
      # decided that only one request would be allowed per student, staff or faculty.
      $query = \Drupal::entityQuery('node');
      $query->condition('type', '3d_print_request');
      $query->condition('field_person_id', $person_id);
      $query->accessCheck(false);
      $requests = $query->execute();
      $no_current_request = true;
      $current_request_markup = '';

      foreach($requests as $request) {
        $r = Node::load($request);
	$s = $r->get('field_print_status')->getValue()[0]['target_id'];
        $status = Term::load($s);
        if(isset($status)) {
	  $status_text = $status->getName();
          if(!($status_text == 'Denied' || $status_text == 'Picked Up' || $status_text == 'Abandoned' || $status_text == 'Postponed')) {
            $no_current_request = false;
            $date_submitted = $r->get('field_date_submitted')->getValue()[0]['value'];
            $current_request_markup = '<p>Your current request was submitted on <i>' . $date_submitted . '</i> and has a status of <i>' . $status_text . '</i>.</p>';
          }
        }
      }

      if($no_current_request) {

        $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree('3d_print_colours');
        $colour_select;
        foreach($terms as $key => $val) {
          $current = \Drupal\taxonomy\Entity\Term::load($val->tid);
          if($current->get('field_in_stock')->value == 'yes') {
            $colour_select[trim($val->tid)] = trim($val->name);
          }
        }
        if($colour_select == null) {
          $colour_select['none'] = 'No Colours Available';
        }

        $form['#attached']['library'][] = 'emerging_technology/et_form';
        $form['request'] = [
          '#page_title' => 'Request a 3D Print',
          '#type' => 'fieldset',
          '#prefix' => '<div id="et-print-request-form">',
          '#suffix' => '</div>',
        ];
        $form['request']['#markup'] = $user_info;
        $form['request']['first_name'] = [
          '#type' => 'hidden',
          '#value' => $fname,
        ];
        $form['request']['last_name'] = [
          '#type' => 'hidden',
          '#value' => $lname,
        ];
        $form['request']['email'] = [
          '#type' => 'hidden',
          '#value' => $email,
        ];
        $form['request']['person_id'] = [
          '#type' => 'hidden',
          '#value' => $person_id,
        ];
        $form['request']['pfsd'] = [
          '#type' => 'textfield',
          '#title' => '<strong>Program / Faculty / School / Department</strong>',
        ];
        $form['request']['course'] = [
          '#type' => 'textfield',
          '#title' => '<strong>Course</strong>',
          '#description' => '<div class="description">Please provide if this print request is related to coursework.</div>'
        ];
        /*$range = array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10);
        $form['request']['copies'] = [
          '#type' => 'select',
          '#options' => $range,
          '#title' => '<strong>Copies:</strong>',
          '#description' => '<div class="description">More than 1 copy may take longer depending on the queue and amount needed.</div>',
        ];*/
        $form['request']['material_primary'] = [
          '#type' => 'select',
          '#title' => '<strong>Colour:</strong>',
          '#options' => $colour_select,
          '#suffix' => '<span class="required">*</span><div id="col-desc-prim"></div>',
          '#default_value' => '',
          '#empty_option' => t('- Select -'),
          '#ajax' => [
            'callback' => 'Drupal\emerging_technology\Form\ETPrintForm::addDescPrim',
            'event' => 'change',
            'wrapper' => 'col-desc-prim',
          ],
        ];
        $form['request']['material_secondary'] = [
          '#type' => 'select',
          '#title' => '<strong>Second colour or material if needed:</strong>',
          '#description' => '<div class="description"><a href="/sites/default/files/ETC/Two_Colour_3D_Printing.pdf" target="_blank">How to set up your object with two colours.</a></div>',
          '#options' => $colour_select,
          '#suffix' => '<div id="col-desc-sec"></div>',
          '#default_value' => '',
          '#empty_option' => t('- Select -'),
          '#ajax' => [
            'callback' => 'Drupal\emerging_technology\Form\ETPrintForm::addDescSec',
            'event' => 'change',
            'wrapper' => 'col-desc-sec',
          ],
        ];
        #$future_date = time() + (3*24*3600); 
        #$form['request']['date_needed'] = [
        #  '#type' => 'date',
        #  '#attributes' => ['min' => \Drupal::service('date.formatter')->format($future_date, 'custom', 'Y-m-d')],
        #  '#date_date_format' =>'l, M j, Y',
        #  '#title' => '<strong>Date needed by:</strong>',
        #  //'#description' => '<div class="description">We will try to finish your print by the date needed or within two weeks.</div>',
        #];
        $form['request']['thingiverse'] =[
          '#type' => 'url',
          '#title' => '<strong>Thingiverse link</strong>',
        ];
        $form['request']['file_upload_fieldset'] = array(
          '#type' => 'fieldset',
          '#title' => '<strong>File Upload</strong>'
        );
        $form['request']['file_upload_fieldset']['file'] = [
          '#type' => 'managed_file',
          '#multiple' => TRUE,
          '#upload_location' => 'private://3d_print_requests/',
          '#upload_validators' => [
            'file_validate_extensions' => ['stl'],
            'file_validate_size' => ['209715200'],
          ],
          '#description' => '<div class="description">Upload one or more .stl files.</div>', 
        ];
        $form['request']['notes'] = [
          '#type' =>'textarea',
          '#title' => '<strong>Notes</strong>',
          '#description' => '<div class="descritpion">Please provide any additional details on printing this object.</div>',
        ];
        $form['request']['copyright'] = [
          '#type' => 'checkbox',
          '#title' => 'I certify that the file I am submitting is in accordance with the <a href="/services/3d-printing/about-3d-printing">printing policy requirements</a> and that the file I am submitting is licensed for this use.<span class="required">*</span>',
        ];
        $form['request']['reuse'] = [
          '#type' => 'checkbox',
          '#title' => 'As the original creator, I give permission to <a href="#" target="_blank">reprint or share this file with attribution.</a>',
        ];
        $form['request']['social'] = [
          '#type' => 'checkbox',
          '#title' => 'I authorize MacEwan Library staff to take and post photos of completed prints for internal assessments and social media for promotion of services.',
        ];
        $form['request']['submit'] = [
          '#type' => 'submit',
          '#title' => 'Submit',
          '#value' => 'Submit',
          '#ajax' => [
            'callback' =>'::ajaxSubmitForm',
            'wrapper' => 'et-print-request-form',
          ],
          '#attributes' => [
            'class' => ['use-ajax'],
          ],
         '#suffix' => '<p>The personal information on this form is collected under s.33(c) of the Freedom of Information and Protection of Privacy Act and will be protected under Part 2 of that Act. It is collected for the purpose of facilitating your 3D print request. Questions concerning this collection should be directed to Privacy and Information Management Office or by email at <a href="mailto:privacy@yourinstitution.com">privacy@yourinstitution.</a>.</p>',
        ];
        $form['#cache']['max-age'] = 0; 
      } else {
        # An existing request has not been completed.
        $markup = $user_info;
        $markup .= '<p>Thank you for your interest in 3D Printing!  Unfortunately it appears as if you already have a print request in process.  In order to help us handle a high amount of interest in this service, we ask that you wait until your current print job is complete before submitting another one.</p>';
        if($current_request_markup != '') {
          $markup .= $current_request_markup;
        }
        $markup .= '<p>If you have a request or question concerning your existing or desired print request, please contact us at <a href="mailto:service_email@yourinstitution.com">service_email@yourinstitution.com</a></p>';

        $form['request'] = [
          '#page_title' => 'Request a 3D Print',
          '#type' => 'fieldset',
          '#prefix' => '<div id="et-print-request-form">',
          '#suffix' => '</div>',
        ];
        $form['request']['#markup'] = $markup;
          
      }
      return $form;
       
    } else {
      # User is anonymous - redirect to login.
      #   Note:  MacEwan uses an in-house developed authentication system and a matching Drupal module.  This system handles
      #          returning a user to this form after authentication.  Since this form was adjusted to use Drupal authentication
      #          as an example, I'm just providing a link here. Using the "Fancy Login" module from drupal.org would make this
      #          a little more convenient.
      $markup = 'Please <a href="' . Url::fromRoute('user.login')->toString() . '">Log In</a> to submit a 3d Print Request.';
      $form['request']['#markup'] = $markup;
      return $form;
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if($form_state->getValue('material_primary') == null) {
      $form_state->setError($form['request']['material_primary'], t('Colour is required'));
    }
    if($form_state->getValue('date_needed') != null) {
      $date = date_create($form_state->getValue('date_needed'));
      $submitted = date_create(date("Y-m-d"));
      $difference = date_diff($submitted, $date);
      $diff = $difference->format('%R%a days');
      if($diff < 4) {
        $form_state->setError($form['request']['date_needed'], t('Select a date at least 3 days from today.'));
      }
    }
    if($form_state->getValue('copyright') == 'no') {
      $form_state->setError($form['request']['copyright'], t('Policy agreement is required'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}


  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if($form_state->hasAnyErrors()) {
      return $form;
    } else {
    // Get colour names form taxonomy id
    $colPrimEnt = \Drupal\taxonomy\Entity\Term::load($form_state->getValue('material_primary'));
    $primary_colour = $colPrimEnt->get('name')->value;
    
    $secondary_colour = '';
    if($form_state->getValue('material_secondary') != null) {
      $colSecEnt = \Drupal\taxonomy\Entity\Term::load($form_state->getValue('material_secondary'));
      $secondary_colour = $colSecEnt->get('name')->value;   
    }


    // Get all other fields
    $fname = $form_state->getValue('first_name');
    $lname = $form_state->getValue('last_name');
    $email = $form_state->getValue('email');
    $person_id = $form_state->getValue('person_id');
    $pfsd = $form_state->getValue('pfsd');
    $course = $form_state->getValue('course');
    #$copies = $form_state->getValue('copies');
    $copies = 1;
    $date = $form_state->getValue('date_needed');
    $thingiverse_link = $form_state->getValue('thingiverse');
    $copyright = $form_state->getValue('copyright');
    $reuse = $form_state->getValue('reuse');
    $social = $form_state->getvalue('social');
    $notes = $form_state->getValue('notes');

    // Get Submitted tid
    // Note:  This step is only here because I couldn't set the default field_print_status value
    //        programmatically on module install.  This is just a time saver on my modulization
    //        efforts.
    $terms = \Drupal::entityManager()->getStorage('taxonomy_term')->loadTree('3d_print_status');
    $submitted = 0;
    foreach($terms as $key => $val) {
      $current = \Drupal\taxonomy\Entity\Term::load($val->tid);
      if($current->getName() == 'Submitted') {
        $submitted = $val->tid;
      }
    }

    // Create a node of content type: 3D print Request
    $date_submitted = date("Y-m-d");
    $node = entity_create('node', [
      'type' => '3d_print_request',
      'title' => '3D Print Request',
      'field_date_submitted' => $date_submitted,
      'field_first_name' => $fname,
      'field_last_name' => $lname,
      'field_email' => $email,
      'field_person_id' => $person_id,
      'field_program_faculty_school_dep' => $pfsd,
      'field_course' => $course,
      'field_number_of_copies' => $copies,
      'field_colour' => $primary_colour,
      'field_secondary_colour_material' => $secondary_colour,
      #'field_date_needed' => [ $date ],
      'field_thingiverse_link' => $thingiverse_link,
      'field_notes' => $notes, 
      'field_copyright' => $copyright,
      'field_reuse' => $reuse,
      'field_permission' => $social,
      #'field_status' => 'submitted',
    ]);

    if($submitted) {
      $node->field_print_status = array('target_id' => $submitted);
    }

    #Add Files to Node
    $files = $form_state->getValue('file');
    if(isset($files)) {
      foreach($files as $file) {
        $node->field_3d_model_file[] = $file;
      }
    }

    $node->save();
    $request_id = $node->id();

    // Email that there is a new submission
    $email_to = 'service_email@yourinstitution.ca';
    $email_from = $email;
    $email_link = \Drupal::request()->getSchemeAndHttpHost() . '/node/' . $request_id;
    $email_message = $fname. ' '.$lname.' has submitted id: <a href="'.$email_link.'">'.$request_id.'</a> for  3D printing.';
    $email_subject = '3D Print Request: '.$request_id;
    $send_mail = new \Drupal\Core\Mail\Plugin\Mail\PhpMail();
    $message['headers'] = [
      'content-type' => 'text/html',
      'MIME-Version' => '1.0',
      'reply-to' => $email_from,
      'from' => $fname.' '.$lname.' <'.$email_from.'>',
    ];
    $message['to'] = $email_to;
    $message['subject'] = $email_subject;
    $message['body'] = $email_message;
    $send_mail->mail($message);

    // Email the submitter that their request has been successfully submitted
    $success_message = '<h2>We got it!</h2><p>Library 3D has received your print request. We will try to finish your print within two weeks. You will be notified by email once your print is ready to pick up. Please contact <a href="mailto:service_email@yourinstitution.com">service_email@yourinstitution.com</a> with any questions.</p>';
    $user_email_to = $email;
    $user_email_from = 'donotreply@yourinstitution.com';
    $user_email_message = $success_message;
    $user_email_subject = '3D Print Request';
    $send_user_mail = new \Drupal\Core\Mail\Plugin\Mail\PhpMail();
    $user_message['headers'] = [
      'content-type' => 'text/html',
      'MIME-Version' => '1.0',
      'reply-to' => $user_email_from,
      'from' => $user_email_from,
    ];
    $user_message['to'] = $user_email_to;
    $user_message['subject'] = $user_email_subject;
    $user_message['body'] = $user_email_message;
    $send_mail->mail($user_message);

    // Displays success message
    $successmsg = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<h2>We got it!</h2><p>Library 3D has received your print request. We will try to finish your print within two weeks. You will be notified by email once your print is ready to pick up. Please contact <a href="mailto:service_email@yourinstitution.com">service_email@yourinstitution.com</a> with any questions.</p>',
      '#attributes' => [
        'id'=>['request-success'],
      ],
    ];

    return $successmsg;
    }

  }

  // Ajax Callback for color select
  public function addDescPrim(array &$form, FormStateInterface $form_state) : array {
    $tidprim = $form_state->getValue('material_primary');
    $coldescprim = '';
    $colimageprim = '';
    $colourprim = \Drupal\taxonomy\Entity\Term::load($tidprim);
    if($tidprim != null) {
      $coldescprim = $colourprim->get('description')->value;
      $colimageprim = file_create_url($colourprim->get('field_image')->entity->uri->value);
    }
    $colhtml = '';
    if($coldescprim != '') {
      $colhtml .= $coldescprim;
    }
    if($colimageprim != '') {
      $altname = $colourprim->getName() . ' Filament';
      $colhtml .= '<img width="200px" alt="'.$altname.'" src="'.$colimageprim.'"/>';
    }
    $descprim = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $colhtml,
      '#attributes' => [
        'id' => ['col-desc-prim'],
      ],
    ];
    return $descprim;
  }

  public function addDescSec(array &$form, FormStateInterface $form_state) : array {
    $tidsec = $form_state->getValue('material_secondary');
    $coldescsec = '';
    $colimagesec = '';
    $coloursec = \Drupal\taxonomy\Entity\Term::load($tidsec);
    if($tidsec != null) {
      $coldescsec = $coloursec->get('description')->value;
      $colimagesec = file_create_url($coloursec->get('field_image')->entity->uri->value);
    }
    $colhtml = '';
    if($coldescsec != '') {
      $colhtml .= $coldescsec;
    }
    if($colimagesec != '') {
      $altname = $coloursec->getName() . ' Filament';
      $colhtml .= '<img width="200px" alt="'.$altname.'" src="'.$colimagesec.'"/>';
    }
    $descsec = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $colhtml,
      '#attributes' => [
        'id' => ['col-desc-sec'],
      ],
    ];
    return $descsec;
  }
}
