<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
$recommended_plugin = get_option( 'hide_folder_recommended_plugin' );
global $current_user;
$email_id = isset($current_user->user_email) ? $current_user->user_email : '';
$upgradeURL = \Folders\Admin\License::get_pro_url();
?>
<div class="folders-footer-menu max-w-[1080px] mx-auto pt-0 pb-5">
    <div class="gap-4 flex justify-between items-center">
        <div class="flex gap-4">
            <a href="https://wordpress.org/support/plugin/folders/" target="_blank" class="text-primaryLight! hover:text-primaryDark! text-sm! font-semibold"><?php esc_html_e('Get Support', 'folders'); ?></a>
            <?php if ( false === $recommended_plugin ) { ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=recommended-folder-plugins')) ?>" class="text-primaryLight! hover:text-primaryDark! text-sm! font-semibold"><?php esc_html_e('Recommended Plugins', 'folders'); ?></a>
            <?php } ?>
            <a href="#" class="text-primaryLight! hover:text-primaryDark! text-sm! font-semibold folders-help-button"><?php esc_html_e('Need help?', 'folders'); ?></a>
        </div>
        <div class="flex gap-1.5 items-center">
            <div class="text-sm text-grey700"><?php esc_html_e('Powered by', 'folders'); ?></div>
            <a href="https://premio.io" target="_blank" class="text-sm! text-primaryLight! hover:text-primaryDark! font-semibold"><?php esc_html_e('Premio', 'folders'); ?></a>
        </div>
    </div>
</div>
<div class="help-fixed-menu group">
    <div class="fixed shadow-lg right-5 rtl:right-auto rtl:left-5 rounded-lg! bottom-29 max-w-75 z-[999] flex flex-col gap-3 opacity-0 invisible translate-y-3 transition-all duration-300 folders-help-menu group-[.form-active]:opacity-100 group-[.form-active]:visible group-[.form-active]:translate-y-0">
        <div class="border-1 border-grey300 rounded-lg! bg-white">
            <form action="" method="post" id="folders-contact-form" >
                <div class="flex py-2 px-3 bg-grey200 gap-5 rounded-t-lg items-center justify-center">
                    <div class="font-normal text-base text-grey900"><?php esc_html_e('Contact Us', 'folders'); ?></div>
                </div>
                <div class="py-2 px-3 text-center">
                    <div class="text-sm text-grey700"><?php esc_html_e('Are you experiencing any issues with Folders? Please let us know, we’d be happy to help 🙏', 'folders'); ?></div>
                </div>
                <div class="h-px w-full"></div>
                <div class="p-3 flex flex-col gap-3.5">
                    <div>
                        <label class="sr-only" for="contact-form-email"><?php esc_html_e('Email', 'folders'); ?></label>
                        <input type="text" name="email" data-field="email" id="contact-form-email" value="<?php echo esc_attr($email_id) ?>" placeholder="<?php esc_html_e('Email', 'folders'); ?>" class="flex is-required is-email w-full border rounded-lg! border-solid border! border-grey300! px-3! h-8! focus-visible:border-grey500! hover:border-grey500!">
                    </div>
                    <div>
                        <label class="sr-only" for="contact-form-message"><?php esc_html_e('Message', 'folders'); ?></label>
                        <textarea type="text" name="message" data-field="message" id="contact-form-message" placeholder="<?php esc_html_e('How can I help you?', 'folders'); ?>" class="flex w-full border rounded-lg! border-solid border! border-grey300! px-3! py-2! min-h-22! focus-visible:border-grey500! hover:border-grey500! is-required"></textarea>
                    </div>
                    <div>
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('folders-contact-form'); ?>">
                        <button type="submit" class="form-button flex items-center gap-1 justify-center relative w-full bg-primaryLight! hover:bg-primaryDark! text-white! text-sm! rounded-lg! px-3! py-2! focus:outline-none! folders-contact-form-button" data-loading="<?php esc_html_e('Sending Message', 'folders'); ?>" data-text="<?php esc_html_e('Chat', 'folders'); ?>">
                            <?php esc_html_e('Chat', 'folders'); ?>
                            <span class="folders-loader"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="fixed right-5 rtl:right-auto rtl:left-5 bottom-29 z-[999] flex flex-col gap-3 opacity-0 invisible translate-y-3 transition-all duration-300 folders-help-menu group-[.active]:opacity-100 group-[.active]:visible group-[.active]:translate-y-0">
        <a href="<?php echo esc_url($upgradeURL)?> " target="_blank" class="relative folder-upgrade-icon w-16 h-16 text-white! rounded-full focus:rounded-full! p-3! shadow-lg! focus:outline-none flex items-center justify-center!">
            <span class="help-button-cta absolute right-full rtl:left-full rtl:right-auto mr-4 rtl:mr-auto rtl:ml-4 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-primaryLight text-white text-sm font-medium rounded shadow-md whitespace-nowrap pointer-events-none before:content-[''] before:absolute before:top-1/2 before:-translate-y-1/2 before:-right-1 before:border-t-[5px] before:border-t-transparent before:border-b-[5px] before:border-b-transparent before:border-l-[5px] before:border-l-primaryLight">
                <?php esc_html_e('Upgrade to Pro', 'folders'); ?>
            </span>
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M29.8376 9.18729C29.5516 8.94852 29.2042 8.79496 28.8351 8.74412C28.466 8.69329 28.0901 8.74723 27.7501 8.89979L21.4251 11.7123L17.7501 5.08729C17.5745 4.77799 17.32 4.52077 17.0126 4.34182C16.7052 4.16288 16.3558 4.0686 16.0001 4.0686C15.6444 4.0686 15.2951 4.16288 14.9877 4.34182C14.6803 4.52077 14.4258 4.77799 14.2501 5.08729L10.5751 11.7123L4.25013 8.89979C3.90952 8.74746 3.53308 8.69344 3.16337 8.74386C2.79366 8.79427 2.44542 8.94711 2.15803 9.18508C1.87064 9.42306 1.65555 9.73669 1.53708 10.0905C1.41861 10.4443 1.40148 10.8242 1.48763 11.1873L4.66263 24.7248C4.72334 24.9869 4.83663 25.2339 4.99563 25.4509C5.15463 25.6679 5.35603 25.8504 5.58763 25.9873C5.90119 26.175 6.25969 26.2743 6.62513 26.2748C6.80278 26.2745 6.9795 26.2492 7.15013 26.1998C12.9374 24.5998 19.0504 24.5998 24.8376 26.1998C25.3661 26.3387 25.928 26.2623 26.4001 25.9873C26.6332 25.8522 26.8356 25.6702 26.9949 25.4529C27.1541 25.2356 27.2665 24.9877 27.3251 24.7248L30.5126 11.1873C30.5978 10.8241 30.5797 10.4444 30.4605 10.091C30.3412 9.73757 30.1255 9.42455 29.8376 9.18729Z" fill="currentColor"/>
            </svg>
        </a>
        <a href="https://wordpress.org/support/plugin/folders/" target="_blank" class="relative bg-primaryLight! hover:bg-primaryDark! text-white! rounded-full focus:rounded-full! p-3! shadow-lg! focus:outline-none flex items-center justify-center!">
            <span class="help-button-cta absolute right-full rtl:left-full rtl:right-auto mr-4 rtl:mr-auto rtl:ml-4 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-primaryLight text-white text-sm font-medium rounded shadow-md whitespace-nowrap pointer-events-none before:content-[''] before:absolute before:top-1/2 before:-translate-y-1/2 before:-right-1 before:border-t-[5px] before:border-t-transparent before:border-b-[5px] before:border-b-transparent before:border-l-[5px] before:border-l-primaryLight">
                <?php esc_html_e('Contact Support', 'folders'); ?>
            </span>
            <img src="<?php echo esc_url( FOLDERS_IMAGE_URL ); ?>footer/help-circle.svg" class="h-10 w-10 relative z-10" alt="Knowledge Base">
        </a>
        <button type="button" class="relative bg-primaryLight! hover:bg-primaryDark! text-white! rounded-full p-3! shadow-lg! focus:outline-none! folders-help-button">
            <span class="help-button-cta absolute right-full rtl:left-full rtl:right-auto mr-4 rtl:mr-auto rtl:ml-4 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-primaryLight text-white text-sm font-medium rounded shadow-md whitespace-nowrap pointer-events-none before:content-[''] before:absolute before:top-1/2 before:-translate-y-1/2 before:-right-1 before:border-t-[5px] before:border-t-transparent before:border-b-[5px] before:border-b-transparent before:border-l-[5px] before:border-l-primaryLight">
                <?php esc_html_e('Contact Us', 'folders'); ?>
            </span>
            <img src="<?php echo esc_url( FOLDERS_IMAGE_URL ); ?>footer/head-phone.svg" class="h-10 w-10 relative z-10" alt="Contact Support">
        </button>
    </div>
    <div class="fixed right-5 bottom-10 z-[999] rtl:right-auto rtl:left-5">
        <button type="button" class="bg-primaryLight! group-[.active]:hidden group-[.form-active]:hidden hover:bg-primaryDark! text-white! rounded-full p-3! shadow-lg! focus:outline-none! folders-menu-button">
            <img src="<?php echo esc_url( FOLDERS_IMAGE_URL ); ?>footer/help-icon.svg" class="h-10 w-10" alt="Tooltip">
        </button>
        <button type="button" class="hidden group-[.active]:block! group-[.form-active]:block! bg-black! hover:bg-black! text-white! rounded-full p-3! shadow-lg! focus:outline-none! folders-menu-button">
            <img src="<?php echo esc_url( FOLDERS_IMAGE_URL ); ?>footer/close.svg" class="h-10 w-10" alt="Tooltip">
        </button>
    </div>
</div>
