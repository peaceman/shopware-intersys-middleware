@component('mail::message')
# Daily Order Overview

@component('mail::table')
| OrderNumber | OrderTime | ArticleNumber | Quantity | ArticleName |
| ------------|:---------:| -------------:|---------:|------------:|
@foreach($orders as $order)
@foreach($order->orderArticles as $orderArticle)
| {{ $order->sw_order_number }} | {{ $order->sw_order_time->format(DateTime::ISO8601) }} | {{ $orderArticle->sw_article_number }} | {{ $orderArticle->sw_quantity }} | {{ $orderArticle->sw_article_name }} |
@endforeach
@endforeach
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
