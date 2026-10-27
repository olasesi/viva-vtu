<?php

namespace Database\Seeders;

use App\Models\FaqArticle;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Getting Started',
                'description' => 'Create your account, fund your wallet and make your first purchase.',
                'icon' => 'rocket',
                'sort_order' => 1,
                'articles' => [
                    [
                        'title' => 'How do I create an account?',
                        'excerpt' => 'Register with your name, email and phone number, then verify your email.',
                        'content' => "Open the registration page and provide your first name, last name, email address and phone number.\n\nChoose a strong password and submit the form. A verification email will be sent to the address you provided. Follow the link in that email to activate your account.\n\nOnce verified, sign in to access your dashboard and wallet.",
                    ],
                    [
                        'title' => 'How do I fund my wallet?',
                        'excerpt' => 'Use Paystack or Flutterwave to top up your wallet instantly.',
                        'content' => "From your dashboard, open Wallet and select Fund Wallet.\n\nEnter the amount you want to add, choose a payment method and complete the checkout. Your wallet is credited automatically once the payment provider confirms the transaction.\n\nIf your balance does not update immediately, wait a few minutes and refresh the page. Contact support if the issue persists.",
                    ],
                    [
                        'title' => 'Why do I need a transaction PIN?',
                        'excerpt' => 'A transaction PIN protects purchases and transfers even if your password is compromised.',
                        'content' => "Your transaction PIN is a four-digit code that authorises purchases and wallet transfers.\n\nSet it from the Security page in your dashboard. When you make a purchase or transfer, you will be prompted for the PIN.\n\nAfter five failed attempts your PIN is temporarily locked for fifteen minutes. You can reset your PIN from the Security page if you know your current PIN.",
                    ],
                ],
            ],
            [
                'name' => 'Purchases and Delivery',
                'description' => 'Airtime, data, electricity, cable TV and exam pins.',
                'icon' => 'shopping-bag',
                'sort_order' => 2,
                'articles' => [
                    [
                        'title' => 'My data or airtime was not delivered',
                        'excerpt' => 'Check the transaction status, then open a dispute if it failed.',
                        'content' => "First check the transaction status in Transaction History. If the status is pending, wait a few minutes and refresh.\n\nIf the status is failed and your wallet was debited, open a dispute from the Disputes page and include the transaction reference. Our support team reviews disputes and refunds confirmed failures to your wallet.",
                    ],
                    [
                        'title' => 'Can I buy for another phone number?',
                        'excerpt' => 'Yes. Enter the recipient number instead of your own when purchasing.',
                        'content' => "Yes. On the airtime or data purchase page, enter the phone number you want to top up. The number does not have to belong to your account.\n\nYou can also save frequent recipients as beneficiaries from the Beneficiaries page to speed up future purchases.",
                    ],
                    [
                        'title' => 'How do I buy electricity units?',
                        'excerpt' => 'Select your electricity provider, enter your meter number and verify before paying.',
                        'content' => "Open Buy Electricity, choose your distribution company and select the meter type.\n\nEnter your meter number and use the verify button to confirm the customer name before paying. After a successful payment the token is shown in the transaction receipt and sent to your notification centre.",
                    ],
                ],
            ],
            [
                'name' => 'Wallet and Security',
                'description' => 'Transfers, disputes, refunds and account security.',
                'icon' => 'shield',
                'sort_order' => 3,
                'articles' => [
                    [
                        'title' => 'How do wallet transfers work?',
                        'excerpt' => 'Transfer to another registered user by email or phone number.',
                        'content' => "Open Wallet and select Transfer. Enter the recipient email address or phone number and the amount.\n\nConfirm the recipient details and authorise the transfer with your transaction PIN. The recipient is credited instantly and both parties receive a receipt.\n\nTransfers between Viva VTU wallets are immediate and cannot be reversed once completed.",
                    ],
                    [
                        'title' => 'I was debited twice for one purchase',
                        'excerpt' => 'Open a dispute with both transaction references and we will investigate.',
                        'content' => "Open the Disputes page and create a dispute for the affected transaction. Include both transaction references in the description.\n\nOur team reconciles duplicate debits with the payment provider. Confirmed duplicates are refunded to your wallet within 24 to 72 hours.",
                    ],
                    [
                        'title' => 'How do I enable two-factor authentication?',
                        'excerpt' => 'Add an authenticator app to protect your login.',
                        'content' => "Open the Security page and choose Set up two-factor authentication.\n\nScan the secret into an authenticator app such as Google Authenticator or Authy, then enter the six-digit code to confirm. Store the recovery codes in a safe place.\n\nIf you lose access to your authenticator, use a recovery code to sign in and then reconfigure two-factor authentication.",
                    ],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $articles = $categoryData['articles'];
            unset($categoryData['articles']);

            $category = FaqCategory::updateOrCreate(
                ['slug' => Str::slug($categoryData['name'])],
                $categoryData,
            );

            foreach ($articles as $index => $article) {
                FaqArticle::updateOrCreate(
                    ['slug' => Str::slug($article['title'])],
                    [
                        'faq_category_id' => $category->id,
                        'title' => $article['title'],
                        'excerpt' => $article['excerpt'],
                        'content' => $article['content'],
                        'sort_order' => $index + 1,
                        'is_published' => true,
                    ],
                );
            }
        }
    }
}
