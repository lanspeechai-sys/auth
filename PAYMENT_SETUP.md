# SchoolLink Africa - Payment Setup Guide

## Paystack Configuration

To enable real payment processing on your e-commerce platform, you need to configure your Paystack API keys.

### Steps to Configure Paystack:

1. **Create a Paystack Account** (if you don't have one)
   - Visit https://paystack.com
   - Sign up for a free account
   - Complete your business verification

2. **Get Your API Keys**
   - Log in to your Paystack Dashboard
   - Navigate to **Settings** → **API Keys & Webhooks**
   - You'll see two sets of keys:
     - **Test Keys** (for development/testing)
     - **Live Keys** (for production/real transactions)

3. **Update Configuration File**
   - Open the file: `config/paystack.php`
   - Replace the placeholder keys with your actual keys:
   
   ```php
   // Test mode keys (for development)
   private const TEST_PUBLIC_KEY = 'pk_test_YOUR_TEST_PUBLIC_KEY_HERE';
   private const TEST_SECRET_KEY = 'sk_test_YOUR_TEST_SECRET_KEY_HERE';
   
   // Live mode keys (for production)
   private const LIVE_PUBLIC_KEY = 'pk_live_YOUR_LIVE_PUBLIC_KEY_HERE';
   private const LIVE_SECRET_KEY = 'sk_live_YOUR_LIVE_SECRET_KEY_HERE';
   ```

4. **Set Payment Mode**
   - During development/testing, keep: `private const USE_LIVE_MODE = false;`
   - For production (real payments), change to: `private const USE_LIVE_MODE = true;`

### Testing Payments

Paystack provides test cards for development:

**Successful Transaction:**
- Card Number: `5531886652142950`
- Expiry: Any future date
- CVV: `564`
- PIN: `3310`
- OTP: `123456`

**Failed Transaction:**
- Card Number: `5060666666666666666`

More test cards: https://paystack.com/docs/payments/test-payments

### Payment Flow

1. **Customer adds items to cart** → `cart.php`
2. **Customer proceeds to checkout** → `checkout.php`
3. **Order is created** in database with "pending" status
4. **Paystack payment initialized** and customer redirected to payment page
5. **Customer completes payment** on Paystack
6. **Callback handler** (`payment_callback.php`) verifies payment
7. **Order status updated** to "paid" and "processing"
8. **Success page** (`payment_success.php`) displays order confirmation
9. **Customers can view orders** at `my_orders.php`

### Webhook Configuration (Optional but Recommended)

For production, set up webhooks to handle payment notifications:

1. In Paystack Dashboard, go to **Settings** → **API Keys & Webhooks**
2. Add webhook URL: `https://yourdomain.com/payment_webhook.php`
3. This allows Paystack to notify your app of payment events

### Security Notes

- **Never commit API keys to version control** (add `config/paystack.php` to `.gitignore`)
- **Keep secret keys confidential** - they should never be exposed in frontend code
- **Use test mode during development** to avoid accidental charges
- **Monitor your Paystack dashboard** for transaction logs and errors

### Troubleshooting

**Payment not completing:**
- Check that your Paystack keys are correct
- Verify your callback URL is accessible
- Check PHP error logs at `admin/system_logs.php`

**Orders showing as pending:**
- Verify payment was successful in Paystack dashboard
- Check that `payment_callback.php` is processing correctly
- Ensure database connection is working

### Support

- **Paystack Documentation:** https://paystack.com/docs
- **Paystack Support:** support@paystack.com
- **Test Card Numbers:** https://paystack.com/docs/payments/test-payments

---

## Other Configuration Files

### Database Configuration (`config/database.php`)
- Update database credentials for your environment
- Default uses localhost, root user, no password

### Email Configuration (Future Enhancement)
- Consider adding email notifications for order confirmations
- Use PHPMailer or similar library

---

**Last Updated:** <?php echo date('Y-m-d'); ?>
