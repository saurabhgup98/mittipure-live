# Test payments on MittiPure

No real money is taken on this site. Every payment option at checkout completes the order, and the order-confirmed WhatsApp is sent once the order is paid.

| Payment option at checkout | What to enter | Result |
|---|---|---|
| Cash on delivery | nothing | Order placed (Processing) |
| Credit Card/Debit Card/NetBanking/UPI (Razorpay) | any card number or UPI ID, e.g. `4111 1111 1111 1111` or `success@razorpay` | Payment succeeds, order Processing |
| Easebuzz Payment Gateway | same as Razorpay | Payment succeeds, order Processing |

## Failing a payment on purpose

Enter this UPI ID in the Razorpay (or Easebuzz) box:

```
failure@razorpay
```

The payment fails, the order is marked **Failed**, any cashback held for it is released, and no WhatsApp is sent. You stay on checkout and can try again.
