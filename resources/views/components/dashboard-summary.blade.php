@props(['metrics', 'leadCount' => 2])
<div class="dashboard-summary">
    <dl class="dashboard-summary__inventory">
        @foreach(array_slice($metrics, 0, $leadCount) as $metric)
            <div class="{{ $loop->first ? 'dashboard-summary__total' : 'dashboard-summary__stored' }}">
                <dt>{{ $metric['label'] }}</dt>
                <dd class="{{ $loop->first ? 'dashboard-summary__figure' : 'dashboard-summary__count' }}">{{ $metric['value'] }}</dd>
                @if($metric['description'])
                    <dd class="dashboard-summary__description">{{ $metric['description'] }}</dd>
                @endif
                @if($metric['note'])
                    <dd class="dashboard-summary__note">{{ $metric['note'] }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
    <dl class="dashboard-summary__details">
        @foreach(array_slice($metrics, $leadCount) as $metric)
            <div class="dashboard-summary__row">
                <dt>{{ $metric['label'] }}</dt>
                <dd class="dashboard-summary__count">{{ $metric['value'] }}</dd>
                @if($metric['description'])
                    <dd class="dashboard-summary__description">{{ $metric['description'] }}</dd>
                @endif
                @if($metric['note'])
                    <dd class="dashboard-summary__note">{{ $metric['note'] }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
</div>
