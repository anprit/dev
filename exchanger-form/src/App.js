import React from 'react';
import './App.css';
import Currency from './Currency'
import Temperature from './Temperature'
import Distance from './Distance'

function App() {
  return (
    <React.Fragment>
      <Currency />
      <Temperature />
      <Distance />
    </React.Fragment>
  );
}

export default App;
