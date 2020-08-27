<?php
/**
 * Plugin Name: Appointments Doctor Back Up
 * Version: 1.0
 * Tested up to: 1.0
 * Requires at least: 1.0
 * WC tested up to: 1.0
 * WC requires at least: 1.0
 * Author: avp
 * Text Domain: appointments_doctor_backup
 */

defined( 'ABSPATH' ) || exit;
global $db_version;
$db_version = '1.3';

if ( ! function_exists( 'dctrbckp_init' ) ) {
	function dctrbckp_init() {
		global $can_create_file;
		$plugin_path = __DIR__ . '/backup/test.txt';
		$fp = fopen( $plugin_path, 'w' );
		if ( false !== $fp ) {
			fclose( $fp );
			unlink( $plugin_path );
			$can_create_file = true;
		} else {
			$can_create_file = false;
			echo '<div style="margin-top:20px;margin-left:160px;width:420px;"><div class="error notice" style="padding:10px">' . __( 'You don\'t have permission to create the file in backup folder', 'appointments_doctor_backup' ) . '<br />' . __( 'Please change backup folder permission to 777', 'appointments_doctor_backup' ) . '</div></div>';
		}
	}
}

if ( ! function_exists( 'dctrbckp_admin_scripts_and_styles' ) ) {
	function dctrbckp_admin_scripts_and_styles() {
		wp_enqueue_style( 'dctrbckp_admin_style', plugins_url( 'css/admin-style.css', __FILE__ ) );
	}
}

if ( ! function_exists( 'dctrbckp_add_admin_menu' ) ) {
	function dctrbckp_add_admin_menu() {
		add_submenu_page( 'edit.php?post_type=wc_appointment', 'Doctors Backup', 'Doctors Backup', 'manage_options', 'doctors_backup', 'dctrbckp_main_page' );
	}
}
/* get distinct doctors id from schedule */
if ( ! function_exists( 'dctrbckp_get_distinct_worktime_user_id' ) ) {
	function dctrbckp_get_distinct_worktime_user_id( $table ) {
		global $wpdb;
		$distinct_id_from_db = $wpdb->get_results( 'SELECT DISTINCT `user_id` FROM `' . $table[0] . '`' , ARRAY_A );
		if ( ! empty ( $distinct_id_from_db ) ) {
			foreach ( $distinct_id_from_db as $value ) {
				$temp_arr[] = $value['user_id'];
			}
			return $temp_arr;
		}
		return array();
		
	}
}
/* get backup file name from path */
if ( ! function_exists( 'dctrbckp_get_file_name' ) ) {
	function dctrbckp_get_file_name( $file_path ) {
		$file_name = explode( '/', $file_path );
		$file_name = $file_name[count( $file_name )-1];
		
		return $file_name;
	}
}
/* get URL for file downloading */
if ( ! function_exists( 'dctrbckp_get_file_url' ) ) {
	function dctrbckp_get_file_url( $file_name ) {
		$file_url = plugin_dir_url( __FILE__ ) . 'backup/' . $file_name;
		return $file_url;
	}
}
/* get userifo by id */
if ( ! function_exists( 'dctrbckp_get_distinct_users' ) ) {
	function dctrbckp_get_distinct_users( array $ids ) {
		global $wpdb;
		if ( empty( $ids ) ) {
			return false;
		}
		asort( $ids );
		/* user info */
		$ids_string = implode( ',', $ids );
		$users_info = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . 'users` WHERE `ID` IN (' . $ids_string . ')', ARRAY_A );
		/* usermeta */
		foreach ( $ids as $value ) {
			$users_meta_info[] = get_user_meta( $value );			
		}
		$counter = 0;
		foreach ( $users_info as &$value ) {
			/* serialize usermeta */
			$value['user_meta'] = serialize( $users_meta_info[ $counter ] );
			$counter++;
		}
		return $users_info;
	}
}
/* export in csv */
if ( ! function_exists( 'dctrbckp_export' ) ) {
	function dctrbckp_export( array $tables, array $doctor = array() ) {
		global $wpdb;
		/*create backup file*/
		$backup_date = date( 'd_m_Y_H_i_s' );
		$current_user = wp_get_current_user();
		$current_user = $current_user->data->user_nicename;
		if ( ! empty( $doctor ) ) {
			$doctor_name_ids = implode( '_', $doctor );
			$backup_file_name = $backup_date . '_' . $current_user . '_doctors_' . $doctor_name_ids . '_backup.csv';
		} else {
			$backup_file_name = $backup_date . '_' . $current_user . '_all_backup.csv';
		}
		$backup_file_path = __DIR__ . '/backup/' . $backup_file_name;	
		if ( isset( $_POST['backup'] ) && isset( $tables ) && isset( $_POST['dctrbckp_form_submit_nonce'] ) && wp_verify_nonce( $_POST['dctrbckp_form_submit_nonce'], 'dctrbckp_form_submit' ) ) {
			
			/* get doctors */
			if ( ! empty( $doctor ) ) {
				/* if 1 doctor selected */
				if ( 1 == count( $doctor ) ) {
					$doctor_temp = implode( $doctor );
				} else {
					$doctor_temp = implode( ',', $doctor );
				}
				$data_from_db = $wpdb->get_results( 'SELECT * FROM ' . $tables[0] . ' WHERE `user_id` IN (' . $doctor_temp . ')', ARRAY_A );
			/* if all doctors */
			} else {
				$data_from_db = $wpdb->get_results( 'SELECT * FROM ' . $tables[0] , ARRAY_A );
			}

			/* get locations */
			$all_locations = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . 'posts` WHERE `post_type`="wc-appointments-loc";', ARRAY_A );
			/* get locations postmeta + attachments with postmeta */
			foreach ( $all_locations as $key => $value ) {
				$all_locations[ $key ]['post_meta'] = get_post_meta( $value['ID'] );
				$all_locations[ $key ]['attachments'] = array();
				if ( '' != $all_locations[ $key ]['post_meta']['external_picture'][0] ) {
					$all_locations[ $key ]['attachments']['external_picture']['ID'] = $all_locations[$key]['post_meta']['external_picture'][0];
					$all_locations[ $key ]['attachments']['external_picture']['url'] = wp_get_attachment_image_url( $all_locations[ $key ]['attachments']['external_picture']['ID'], 'full' );
					$image_post = $wpdb->get_row( 'SELECT `post_content`, `post_excerpt`, `post_title`, `post_type`, `post_status` FROM `' . $wpdb->prefix . 'posts` WHERE `ID`="' . $all_locations[ $key ]['attachments']['external_picture']['ID'] . '"', ARRAY_A );
					$all_locations[ $key ]['attachments']['external_picture']['description'] = isset( $image_post['post_content'] ) ? $image_post['post_content'] : '';
					$all_locations[ $key ]['attachments']['external_picture']['caption'] = isset( $image_post['post_excerpt'] ) ? $image_post['post_excerpt'] : '';
					$all_locations[ $key ]['attachments']['external_picture']['alt'] = isset( get_post_meta( $all_locations[ $key ]['attachments']['external_picture']['ID'], '_wp_attachment_image_alt' )[0] ) ? get_post_meta( $all_locations[ $key ]['attachments']['external_picture']['ID'], '_wp_attachment_image_alt' )[0] : '';
					$all_locations[ $key ]['attachments']['external_picture']['title'] = isset( $image_post['post_title'] ) ? $image_post['post_title'] : '';
					$all_locations[ $key ]['attachments']['external_picture']['type'] = isset( $image_post['post_type'] ) ? $image_post['post_type'] : '';
					$all_locations[ $key ]['attachments']['external_picture']['status'] = isset( $image_post['post_status'] ) ? $image_post['post_status'] : '';
				}
				if ( '' != $all_locations[ $key ]['post_meta']['internal_picture'][0] ) {
					$all_locations[ $key ]['attachments']['internal_picture']['ID'] = $all_locations[ $key ]['post_meta']['internal_picture'][0];
					$all_locations[ $key ]['attachments']['internal_picture']['url'] = wp_get_attachment_image_url( $all_locations[ $key ]['attachments']['internal_picture']['ID'], 'full' );
					$image_post = $wpdb->get_row( 'SELECT `post_content`, `post_excerpt`, `post_title`, `post_type`, `post_status` FROM `' . $wpdb->prefix . 'posts` WHERE `ID`="' . $all_locations[ $key ]['attachments']['internal_picture']['ID'] . '"', ARRAY_A );
					$all_locations[ $key ]['attachments']['internal_picture']['description'] = isset( $image_post['post_content'] ) ? $image_post['post_content'] : '';
					$all_locations[ $key ]['attachments']['internal_picture']['caption'] = isset( $image_post['post_excerpt'] ) ? $image_post['post_excerpt'] : '';
					$all_locations[ $key ]['attachments']['internal_picture']['alt'] = isset( get_post_meta( $all_locations[ $key ]['attachments']['internal_picture']['ID'], '_wp_attachment_image_alt' )[0] ) ? get_post_meta( $all_locations[ $key ]['attachments']['internal_picture']['ID'], '_wp_attachment_image_alt' )[0] : '';
					$all_locations[ $key ]['attachments']['internal_picture']['title'] = isset( $image_post['post_title'] ) ? $image_post['post_title'] : '';
					$all_locations[ $key ]['attachments']['internal_picture']['type'] = isset( $image_post['post_type'] ) ? $image_post['post_type'] : '';
					$all_locations[ $key ]['attachments']['internal_picture']['status'] = isset( $image_post['post_status'] ) ? $image_post['post_status'] : '';
				}

				/* serialize arrays */
				$all_locations[ $key ]['post_meta'] = serialize( $all_locations[ $key ]['post_meta'] );
				$all_locations[ $key ]['attachments'] = serialize( $all_locations[ $key ]['attachments'] );
			}

			/* get brands */
			$all_brands = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . 'posts` WHERE `post_type`="appointments-brand";', ARRAY_A );
			/* get brands postmeta + attachment with postmeta */
			foreach ( $all_brands as $key => $value ) {
				$all_brands[ $key ]['post_meta'] = get_post_meta( $value['ID'] );
				$all_brands[ $key ]['attachments'] = array();
				if ( isset( $all_brands[ $key ]['post_meta']['brand_logo'][0] ) ) {
					$all_brands[ $key ]['attachments']['brand_logo']['ID'] = $all_brands[ $key ]['post_meta']['brand_logo'][0];
					$all_brands[ $key ]['attachments']['brand_logo']['url'] = wp_get_attachment_image_url( $all_brands[ $key ]['attachments']['brand_logo']['ID'], 'full' );
					$image_brand_post = $wpdb->get_row( 'SELECT `post_content`, `post_excerpt`, `post_title`, `post_type`, `post_status` FROM `' . $wpdb->prefix . 'posts` WHERE `ID`="' . $all_brands[ $key ]['attachments']['brand_logo']['ID'] . '"', ARRAY_A );
					$all_brands[ $key ]['attachments']['brand_logo']['description'] = isset( $image_brand_post['post_content'] ) ? $image_brand_post['post_content'] : '';
					$all_brands[ $key ]['attachments']['brand_logo']['caption'] = isset( $image_brand_post['post_excerpt'] ) ? $image_brand_post['post_excerpt'] : '';
					$all_brands[ $key ]['attachments']['brand_logo']['alt'] = isset( get_post_meta( $all_brands[ $key ]['attachments']['brand_logo']['ID'], '_wp_attachment_image_alt' )[0] ) ? get_post_meta( $all_brands[ $key ]['attachments']['brand_logo']['ID'], '_wp_attachment_image_alt' )[0] : '';
					$all_brands[ $key ]['attachments']['brand_logo']['title'] = isset( $image_brand_post['post_title'] ) ? $image_brand_post['post_title'] : '';
					$all_brands[ $key ]['attachments']['brand_logo']['type'] = isset( $image_brand_post['post_type'] ) ? $image_brand_post['post_type'] : '';
					$all_brands[ $key ]['attachments']['brand_logo']['status'] = isset( $image_brand_post['post_status'] ) ? $image_brand_post['post_status'] : '';
				}
				
				/* serialize arrays */
				$all_brands[ $key ]['post_meta'] = serialize( $all_brands[ $key ]['post_meta'] );
				$all_brands[ $key ]['attachments'] = serialize( $all_brands[ $key ]['attachments'] );
			}

			/* locations+brands table */
			$locations_and_brands = $wpdb->get_results( 'SELECT * FROM `' . $wpdb->prefix . 'wc_appointment_location_brand`;', ARRAY_A );
			
			/* add locations+brands table */
			$all_brands[] = array( serialize( $locations_and_brands ) );
			
			/*creating and filling file*/
			$fp = fopen( $backup_file_path, 'w' );
			if ( false !== $fp ) {
				foreach ( $data_from_db as $key => $value ) {
					if ( 0 == $key ) {
						/* marker start of schedule */
						fputcsv( $fp, array( '--startschedule--' ) );
						fputcsv( $fp, array_keys( $value ) );
					}
					fputcsv( $fp, $value );
				}	
				/* marker end of schedule */
				fputcsv( $fp, array( '--endschedule--' ) );

				/* add user info */
				if ( ! empty( $doctor ) ) {
					$ids = $doctor;
				} else {
					$ids = dctrbckp_get_distinct_worktime_user_id( $tables );
				}
				$attachment_users_info = dctrbckp_get_distinct_users( $ids );
				foreach ( $attachment_users_info as $key => $value ) {
					if ( 0 == $key ) {
						fputcsv( $fp, array( '--startuser--' ) );
						fputcsv( $fp, array_keys( $value ) );
					}
					fputcsv( $fp, $value );
				}
				fputcsv( $fp, array( '--enduser--' ) );

				/* write locations */
				foreach ( $all_locations as $key => $value ) {
					if ( 0 == $key ) {
						fputcsv( $fp, array( '--startlocations--' ) );
						fputcsv( $fp, array_keys( $value ) );
					}
					fputcsv( $fp, $value );
				}
				fputcsv( $fp, array( '--endlocations--' ) );
				
				/* write brands */
				foreach ( $all_brands as $key => $value ) {
					if ( 0 == $key ) {
						fputcsv( $fp, array( '--startbrands--' ) );
						fputcsv( $fp, array_keys( $value ) );
					}
					fputcsv( $fp, $value );
				}
				fputcsv( $fp, array( '--endbrands--' ) );
			} else {
				/* if the file does not created */
				return false;
			}
			fclose( $fp );
		}
		return $backup_file_path;
	}
}
/* import from csv */
if ( ! function_exists( 'dctrbckp_import' ) ) {
	function dctrbckp_import( $file_path ) {
		global $wpdb;
		/* array for markers */
		$default_strings = array( '--startschedule--', '--endschedule--', '--startuser--', '--enduser--', '--startlocations--', '--endlocations--', '--startbrands--', '--endbrands--', 'work_time_id', 'ID' );
		$fp = fopen( $file_path, 'r' );
		if ( false !== $fp ) {
			/* empty arrays for data */
			$data = array();
			$data_user = array();
			$data_locations = array();
			$data_brands = array();
			$data_locations_and_brands = array();
			/* marker end of schedules in file */
			$find_end_of_schedule = false;
			$find_end_of_users = false;
			$find_end_of_locations = false;
			/* get data from file and add it to array */
			while ( ( $temp = fgetcsv( $fp, 10000, ',' ) ) !== false ) {
				$while_flag = false;
				if ( in_array( $temp[0], $default_strings ) ) {
					switch ( $temp[0] ) {						
						case 'ID':
							if ( ! $find_end_of_users ) {
								/* if user block start */
								$find_end_of_schedule = true;
								/* user headings for DB */
								$data_user_heading = $temp;
								$while_flag = true;
							} elseif ( $find_end_of_locations ) {
								/* brands headings for DB */
								$data_brands_heading = $temp;
								$find_end_of_users = false;
								$while_flag = true;
							} else {
								/* locations headings for DB */
								$data_locations_heading = $temp;
								$while_flag = true;
							}
							break;
						case 'work_time_id':
							/* schedule headings for DB */
							$data_heading = $temp;
							$while_flag = true;
							break;
						case '--startbrands--':
							$find_end_of_locations = true;
							$while_flag = true;
							break;
						case '--startlocations--':
							$find_end_of_users = true;
							$find_end_of_schedule = false;
							$while_flag = true;
							break;
						case '--endbrands--':
						case '--startschedule--':
						case '--endschedule--':
						case '--startuser--':
						case '--enduser--':
						case '--endlocations--':
						default:
							$while_flag = true;
							break;
					}
					if ( true === $while_flag ) {
						continue;
					}
				}
				if ( $find_end_of_schedule ) {
					/* unserialize usermeta */
					$counted = count( $temp );
					$temp[ $counted - 1 ] = unserialize( $temp[ $counted - 1 ] );
					$data_user[] = $temp;
				} elseif ( $find_end_of_users ) {
					$counted = count( $temp );
					$temp[ $counted - 1 ] = unserialize( $temp[ $counted - 1 ] );
					$temp[ $counted - 2 ] = unserialize( $temp[ $counted - 2 ] );
					$data_locations[] = $temp;
				} elseif ( $find_end_of_locations ) {
					/* take locations and brands array  */
					if ( is_serialized( $temp[0] ) && 1 == count( $temp ) ) {
						$data_locations_and_brands = unserialize( $temp[0] );
						unset( $temp );
						continue;
					}
					$counted = count( $temp );
					$temp[ $counted - 1 ] = unserialize( $temp[ $counted - 1 ] );
					$temp[ $counted - 2 ] = unserialize( $temp[ $counted - 2 ] );
					$data_brands[] = $temp;
				} else {
					$data[] = $temp;
				}
			}

			/* merge arrays for insert schedules */
			foreach ( $data as $value ) {
				$data_combined_array[] = array_combine( $data_heading, $value );
			}

			/* merge arrays for insert users */
			foreach ( $data_user as $value ) {
				$data_user_combined_array[] = array_combine( $data_user_heading, $value );
			}

			/* merge arrays for insert locations */
			foreach ( $data_locations as $value ) {
				$data_locations_combined_array[] = array_combine( $data_locations_heading, $value );
			}

			/* merge arrays for insert brands */
			foreach ( $data_brands as $value ) {
				$data_brands_combined_array[] = array_combine( $data_brands_heading, $value );
			}

			/* add locations */
			if ( ! empty( $data_locations_combined_array ) ) {
				foreach ( $data_locations_combined_array as $key => $value ) {
					$found_post = post_exists( $value['post_title'], '', '', $value['post_type'] );
					if ( ! $found_post ) {
						$params  = array(
							'post_title'	=> $value['post_title'],
							'post_type'		=> $value['post_type'],
							'post_status'	=> $value['post_status']
						);
						$new_location_id[ $key ] = wp_insert_post( $params );
						$id_for_change_locations[ $value['ID'] ] = $new_location_id[ $key ];
						if ( ! empty( $value['post_meta']['coordinates'][0] ) ) {
							add_post_meta( $new_location_id[ $key ], 'coordinates', $value['post_meta']['coordinates'][0], true );
						}
						/* attachments for locations external/internal + meta */
						foreach ( $value['attachments'] as $key2 => $value2 ) {
							$wp_upload_dir = wp_upload_dir();
							$filename_ = $value2['ID'];
							if ( ! empty( $filename_ ) && strpos( $filename_, 'http' ) ) {
								$imgs = $wp_upload_dir['path'] . '/' . basename( $filename_ );
								if ( file_exists( $imgs ) ) {
									file_put_contents( $imgs, file_get_contents( $filename_ ) );
									$filetype = wp_check_filetype( basename( $filename_ ), null );
									$attachment = array(
										'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename_ ), 
										'post_mime_type' => $filetype['type'],
										'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename_ ) ),
										'post_content'   => '',
										'post_status'    => $value2['status']
									);
									$new_location_attachments_id[ $key ][ $key2 ] = wp_insert_attachment( $attachment, $filename_, $value['ID'] );
									/* Make sure that this file is included, as wp_generate_attachment_metadata() depends on it. */
									require_once( ABSPATH . 'wp-admin/includes/image.php' );
									/* Generate the metadata for the attachment, and update the database record. */
									$attach_data = wp_generate_attachment_metadata( $new_location_attachments_id[ $key ][ $key2 ], $imgs );
									wp_update_attachment_metadata( $new_location_attachments_id[ $key ][ $key2 ], $attach_data );
								}
							}
						}
						/* update-add post_meta */
						$value['post_meta']['location_city'][0] = isset( $value['post_meta']['location_city'][0] ) ? $value['post_meta']['location_city'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_city', $value['post_meta']['location_city'][0] );
						$value['post_meta']['location_address'][0] = isset( $value['post_meta']['location_address'][0] ) ? $value['post_meta']['location_address'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_address', $value['post_meta']['location_address'][0] );
						$value['post_meta']['location_map_url'][0] = isset( $value['post_meta']['location_map_url'][0] ) ? $value['post_meta']['location_map_url'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_map_url', $value['post_meta']['location_map_url'][0] );
						$value['post_meta']['location_post_code'][0] = isset( $value['post_meta']['location_post_code'][0] ) ? $value['post_meta']['location_post_code'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_post_code', $value['post_meta']['location_post_code'][0] );
						$value['post_meta']['location_latitude'][0] = isset( $value['post_meta']['location_latitude'][0] ) ? $value['post_meta']['location_latitude'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_latitude', $value['post_meta']['location_latitude'][0] );
						$value['post_meta']['location_longitude'][0] = isset( $value['post_meta']['location_longitude'][0] ) ? $value['post_meta']['location_longitude'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_longitude', $value['post_meta']['location_longitude'][0] );
						$value['post_meta']['location_custom_latitude'][0] = isset( $value['post_meta']['location_custom_latitude'][0] ) ? $value['post_meta']['location_custom_latitude'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_custom_latitude', $value['post_meta']['location_custom_latitude'][0] );
						$value['post_meta']['location_custom_longitude'][0] = isset( $value['post_meta']['location_custom_longitude'][0] ) ? $value['post_meta']['location_custom_longitude'][0] : '';
						add_post_meta( $new_location_id[ $key ], 'location_custom_longitude', $value['post_meta']['location_custom_longitude'][0] );
					} else {
						$new_location_id[ $key ] = $found_post;
						$id_for_change_locations[ $value['ID'] ] = $new_location_id[ $key ];
					}
				}
			}
			
			/* add brands */
			if ( ! empty( $data_brands_combined_array ) ) {
				foreach ( $data_brands_combined_array as $key => $value ) {
					$found_post = post_exists( $value['post_title'], '', '', $value['post_type'] );
					if ( ! $found_post ) {
						$params  = array(
							'post_title'	=> $value['post_title'],
							'post_type'		=> $value['post_type'],
							'post_status'	=> $value['post_status']
						);
						$new_brand_id[ $key ] = wp_insert_post( $params );
						$id_for_change_brands[ $value['ID'] ] = $new_brand_id[ $key ];
						//echo '<pre>'; var_dump( unserialize( $value['post_meta']['brand_color'][0] ) ); die();
						if ( ! empty( $value['post_meta']['brand_booking_url'][0] ) ) {
							add_post_meta( $new_brand_id[ $key ], 'brand_booking_url', $value['post_meta']['brand_booking_url'][0], true );
						}
						/* attachments for brands + meta */
						foreach ( $value['attachments'] as $key2 => $value2 ) {
							$wp_upload_dir = wp_upload_dir();
							$filename_ = $value2['url'];
							if ( ! empty( $filename_ ) ) {
								$imgs = $wp_upload_dir['path'] . '/' . basename( $filename_ );
								if ( file_exists( $imgs ) ) {
									file_put_contents( $imgs, file_get_contents( $filename_ ) );
									$filetype = wp_check_filetype( basename( $filename_ ), null );
									$attachment = array(
										'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename_ ), 
										'post_mime_type' => $filetype['type'],
										'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename_ ) ),
										'post_content'   => '',
										'post_status'    => $value2['status']
									);
									$new_brands_attachments_id[ $key ][ $key2 ] = wp_insert_attachment( $attachment, $filename_, $value['ID'] );
									/* Make sure that this file is included, as wp_generate_attachment_metadata() depends on it. */
									require_once( ABSPATH . 'wp-admin/includes/image.php' );
									/* Generate the metadata for the attachment, and update the database record. */
									$attach_data = wp_generate_attachment_metadata( $new_brands_attachments_id[ $key ][ $key2 ], $imgs );
									wp_update_attachment_metadata( $new_brands_attachments_id[ $key ][ $key2 ], $attach_data );
								}
							} else {
								continue;
							}
						}
						if ( ! empty( $value['post_meta']['brand_logo'][0] ) && isset( $new_brands_attachments_id[ $key ]['brand_logo'] ) ) {
							add_post_meta( $new_brand_id[ $key ], 'brand_logo', $new_brands_attachments_id[ $key ]['brand_logo'], true );
						} else {
							add_post_meta( $new_brand_id[ $key ], 'brand_logo', '', true );
						}
						/* update-add post_meta */
						add_post_meta( $new_brand_id[ $key ], 'brand_email', $value['post_meta']['brand_email'][0] );
						add_post_meta( $new_brand_id[ $key ], 'brand_phone', $value['post_meta']['brand_phone'][0] );
						add_post_meta( $new_brand_id[ $key ], 'brand_color', maybe_unserialize( $value['post_meta']['brand_color'][0] ) );
					} else {
						$new_brand_id[ $key ] = $found_post;
						$id_for_change_brands[ $value['ID'] ] = $new_brand_id[ $key ];
					}
				}
			}
			
			/* add users if dont exists in DB */
			if ( ! empty( $data_user_combined_array ) ) {
				foreach ( $data_user_combined_array as $value ) {
					if ( username_exists( $value['user_login'] ) ) {
						/* remove all schedules */
						if ( isset( $_POST['replace_schedule'] ) && 1 == $_POST['replace_schedule'] ) {
							$wpdb->query( 'DELETE FROM `' . $wpdb->prefix . 'wc_appointment_doctor_work_time` WHERE `user_id` = ' . $value['ID'] );
						}
						continue;
					} else {
						$userdata = array(
							'user_pass'       => $value['user_pass'],
							'user_login'      => $value['user_login'],
							'user_nicename'   => $value['user_nicename'],
							'user_url'        => $value['user_url'],
							'user_email'      => $value['user_email'],
							'display_name'    => $value['display_name'],
							'nickname'        => isset( $value['user_meta']['nickname'][0] ) ? $value['user_meta']['nickname'][0] : '',
							'first_name'      => isset( $value['user_meta']['first_name'][0] ) ? $value['user_meta']['first_name'][0] : '',
							'last_name'       => isset( $value['user_meta']['last_name'][0] ) ? $value['user_meta']['last_name'][0] : '',
							'description'     => isset( $value['user_meta']['description'][0] ) ? $value['user_meta']['description'][0] : '',
							'rich_editing'    => isset( $value['user_meta']['rich_editing'][0] ) ? $value['user_meta']['rich_editing'][0] : '',
							'user_registered' => $value['user_registered'],
							'role'            => '',
							'jabber'          => '',
							'aim'             => '',
							'yim'             => '',
						);
						$result = wp_insert_user( $userdata );
						if ( ! is_wp_error( $result ) ) {
							/* add role in usermeta */
							if ( isset( $value['user_meta']['wp_capabilities'][0] ) ) {
								update_user_meta( $result, 'wp_capabilities', unserialize( $value['user_meta']['wp_capabilities'][0] ) );
							}
							/* array with IDs for replacing */
							$id_for_change[ $value['ID'] ] = $result; 
						} else {
							echo $result->get_error_message();
							die();
						}
					}
				}
			}
			
			/* replace old IDs for new IDs */
			if ( ! empty( $id_for_change ) ) {
				foreach ( $data_combined_array as &$row ) {
					foreach ( $id_for_change as $key => $value ) {
						$row['user_id'] = ( $key == $row['user_id'] ) ? $value : $row['user_id'];
					}
					foreach ( $id_for_change_locations as $key => $value ) {
						$row['work_location_id'] = ( $key == $row['work_location_id'] ) ? $value : $row['work_location_id'];
					}
				}
			}
			/* prepare locations and brand array replace old IDs for new IDs */
			foreach ( $data_locations_and_brands as $key => &$row ) {
				foreach ( $id_for_change_locations as $key2 => $value ) {
					$row['location_id'] = ( $key2 == $row['location_id'] ) ? $value : $row['location_id'];
				}
				foreach ( $id_for_change_brands as $key2 => $value ) {
					$row['brand_id'] = ( $key2 == $row['brand_id'] ) ? $value : $row['brand_id'];
				}
			}
			/* insert in schedules table */
			$array_start = array();
			$array_end = array();
			$table = $wpdb->prefix . 'wc_appointment_doctor_work_time';
			$format = array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' );
			$table_result = $wpdb->prefix . 'wc_appointments_availability';
			$format_result = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' );
			foreach ( $data_combined_array as $value ) {
				unset( $value['work_time_id'] );
				$wpdb->insert( $table, $value, $format );
				
				$array_start[ $value['user_id'] ][] = $value['work_start_date']; 
				$array_end[ $value['user_id'] ][] = $value['work_end_date'];

				if ( isset( $array_start[ $value['user_id'] ] ) && in_array( $value['work_start_date'], $array_start[ $value['user_id'] ] ) && isset( $array_end[ $value['user_id'] ] ) && in_array( $value['work_end_date'], $array_end[ $value['user_id'] ] ) ) {
					continue;
				}
				/* insert in availability table */
				$schedule_to_db = array(
					'kind'			=> 'availability#staff',
					'kind_id'       => $value['user_id'],
					'range_type'	=> 'custom',
					'from_date'		=> $value['work_start_date'],
					'to_date'		=> $value['work_end_date'],
					'from_range'	=> $value['work_start_date'],
					'to_range'		=> $value['work_end_date'],
					'appointable'   => 'yes',
					'priority'      => 10,
					'qty'           => '',
					'ordering'      => 0,
					);
				$wpdb->insert( $table_result, $schedule_to_db, $format_result );
			}
			/* insert in location_brand table */
			$table = $wpdb->prefix . 'wc_appointment_location_brand';
			$format = array( '%d', '%d', '%s' );
			foreach ( $data_locations_and_brands as $value ) {
				unset( $value['location_brand_id'] );
				$wpdb->insert( $table, $value, $format );
			}
			fclose( $fp );
		}
		return true;
	}
}
if ( ! function_exists( 'dctrbckp_main_page' ) ) {
	function dctrbckp_main_page() {
		global $wpdb, $can_create_file;
		if ( isset( $_POST['dctrbckp_form_submit_nonce'] ) && wp_verify_nonce( $_POST['dctrbckp_form_submit_nonce'], 'dctrbckp_form_submit' ) ) {
			/* export */
			if ( isset( $_POST['tables']['export'] ) ) {
				$file_path = dctrbckp_export( array( $_POST['tables']['export'] ) );
				if ( false !== $file_path ) {
					$file_name = dctrbckp_get_file_name( $file_path );
					if ( ! empty( $file_name ) ) {
						$export_file_url = dctrbckp_get_file_url( $file_name );
						echo '<div><div class="update-nag">' . sprintf( __( 'Backup file "%s"  successfully created!', 'appointments_doctor_backup' ), $file_name ) . '<br /><a href="' . $export_file_url . '" download>' . __( 'Download this file', 'appointments_doctor_backup' ) . '</a></div></div>';
					}
				} else {
					echo '<div><div class="update-nag">' . __( 'Backup file can\'t be created ', 'appointments_doctor_backup' ) . '<br />' . __( 'Please change backup folder permission to 777', 'appointments_doctor_backup' ) . '</div></div>';
					die();
				}
			/* import */
			} elseif ( ! empty( $_FILES['import_all']['name'] ) && ! empty( $_FILES['import_all']['tmp_name'] ) ) {
				$target_dir = dirname( __FILE__ ) . '/backup/';
				$target_file = $target_dir . $_FILES['import_all']['name'];
				if ( move_uploaded_file( $_FILES['import_all']['tmp_name'], $target_file ) ) {
					$check = dctrbckp_import( $target_file );
					if ( true === $check ) {
						_e( 'All schedule was imported.', 'appointments_doctor_backup' );
					}
				} else {
					_e( 'Sorry, there was an error uploading your file.', 'appointments_doctor_backup' );
				}
			/* export selected doctors */
			} elseif ( ! empty( $_POST['tables']['export_one'] ) ) {
				$doctor = $_POST['tables']['export_one'];
				$file_path = dctrbckp_export( array( $wpdb->prefix . 'wc_appointment_doctor_work_time' ), $doctor );
				$file_name = dctrbckp_get_file_name( $file_path );
				if ( ! empty( $file_name ) ) {
					$export_file_url = dctrbckp_get_file_url( $file_name );
					echo '<div><div class="update-nag">' . sprintf( __( 'Backup file "%s"  successfully created!', 'appointments_doctor_backup' ), $file_name ) . '<br /><a href="' . $export_file_url . '" download>' . __( 'Download this file', 'appointments_doctor_backup' ) . '</a></div></div>';
				}
			}
		}
		$tables = $wpdb->prefix . 'wc_appointment_doctor_work_time';
		$ids = dctrbckp_get_distinct_worktime_user_id( array( $tables ) );
		if ( isset( $ids ) ) {
			$attachment_users_info = dctrbckp_get_distinct_users( $ids );
		}
		?>
		<h2><?php _e( 'Export', 'appointments_doctor_backup' ); ?></h2>
		<form action="" method="post" class="backup-form" enctype="multipart/form-data">
			<label for=""><?php _e( 'Export all', 'appointments_doctor_backup' ); ?>
				<input type="radio" name="tables[export]" value="<?php echo $wpdb->prefix . 'wc_appointment_doctor_work_time'; ?>" />
			</label>
			<?php
			if ( ! empty( $attachment_users_info ) ) {
				?>
				<h3><?php _e( 'Export one doctor', 'appointments_doctor_backup' ); ?></h3>
				<?php
				foreach ( $attachment_users_info as $value ) {
					?>
					<label for="" class="doctor-box"><?php echo $value['display_name']; ?>
						<input type="checkbox" name="tables[export_one][<?php echo $value['display_name']; ?>]" value="<?php echo $value['ID']; ?>" />
					</label>
					<?php
				}
			}
			?>
			<h2><?php _e( 'Import', 'appointments_doctor_backup' ); ?></h2>
			<label for="replace_schedule"><?php _e( 'Replace the existing schedule for a doctor', 'appointments_doctor_backup' ); ?>
				<input type="radio" id="replace_schedule" name="replace_schedule" value="1" />
			</label>
			<label for="import_all"><?php _e( 'Select file for import', 'appointments_doctor_backup' ); ?>
				<input type="file" id="import_all" name="import_all" />
			</label>
			<?php wp_nonce_field( 'dctrbckp_form_submit', 'dctrbckp_form_submit_nonce' ); ?>
			<input type="submit" name="backup" <?php echo $can_create_file ? '' : 'disabled' ?> />
		</form>
	<?php
	}
}
add_action( 'init', 'dctrbckp_init' );
add_action( 'admin_enqueue_scripts', 'dctrbckp_admin_scripts_and_styles' );
add_action( 'admin_menu', 'dctrbckp_add_admin_menu', 100 );