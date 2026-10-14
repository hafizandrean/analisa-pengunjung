import React from 'react';
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend
} from 'recharts';
import { DailyTrafficItem } from '../../types/api';

interface DailyTrendChartProps {
  data: DailyTrafficItem[];
}

export const DailyTrendChart: React.FC<DailyTrendChartProps> = ({ data }) => {
  return (
    <div className="h-80 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart
          data={data}
          margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
        >
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
          <XAxis
            dataKey="day_name"
            tick={{ fontSize: 12, fill: '#64748b' }}
            tickLine={false}
            axisLine={{ stroke: '#cbd5e1' }}
          />
          <YAxis
            tick={{ fontSize: 12, fill: '#64748b' }}
            tickLine={false}
            axisLine={{ stroke: '#cbd5e1' }}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: '#0f172a',
              borderRadius: '0.75rem',
              border: 'none',
              color: '#f8fafc',
              fontSize: '0.75rem',
              boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.2)'
            }}
            labelStyle={{ fontWeight: 'bold', color: '#f1f5f9' }}
            formatter={(val: number, name: string) => {
              const label = name === 'count_in' ? 'Masuk' : name === 'count_out' ? 'Keluar' : 'Total';
              return [`${val} orang`, label];
            }}
          />
          <Legend
            verticalAlign="top"
            height={36}
            formatter={(value) => {
              if (value === 'count_in') return <span className="text-xs font-medium text-emerald-700">Masuk (IN)</span>;
              if (value === 'count_out') return <span className="text-xs font-medium text-blue-700">Keluar (OUT)</span>;
              return value;
            }}
          />
          <Bar dataKey="count_in" fill="#10b981" radius={[4, 4, 0, 0]} name="count_in" />
          <Bar dataKey="count_out" fill="#3b82f6" radius={[4, 4, 0, 0]} name="count_out" />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
};
